<?php

namespace App\Services\Restaurant;

use App\Contracts\KitchenWasteServiceInterface;
use App\Contracts\Repositories\KitchenWasteRepositoryInterface;
use App\Enums\KitchenWasteReasonEnum;
use App\Enums\KitchenWasteStatusEnum;
use App\Http\Resources\Restaurant\KitchenWasteResource;
use App\Models\Dish;
use App\Models\InventoryMovement;
use App\Models\KitchenWaste;
use App\Models\Product;
use App\Models\ProductLot;
use App\Observers\ProductLotObserver;
use App\Services\Inventory\StockoutService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KitchenWasteService implements KitchenWasteServiceInterface
{
    public function __construct(
        protected KitchenWasteRepositoryInterface $wasteRepository
    ) {}

    /**
     * Lista las mermas paginadas utilizando KitchenWasteResource y métricas de resumen.
     */
    public function listWastes(array $filters = []): array
    {
        $paginator = $this->wasteRepository->paginate($filters);
        $metrics = $this->wasteRepository->getSummaryMetrics($filters);

        return [
            'data' => KitchenWasteResource::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'summary' => $metrics,
        ];
    }

    /**
     * Registra un reporte de merma en estado pendiente (sin descontar inventario hasta ser aprobado).
     */
    public function recordWaste(array $data, int $userId, ?UploadedFile $photo = null): KitchenWaste
    {
        return DB::transaction(function () use ($data, $userId, $photo) {
            $itemType = $data['item_type'] ?? 'product';
            $qty = (float) $data['quantity'];
            if ($qty <= 0) {
                throw new InvalidArgumentException('La cantidad de merma debe ser mayor a 0.');
            }

            $unitCost = 0.0;
            $productId = null;
            $dishId = null;
            $lotId = $data['lot_id'] ?? null;

            if ($itemType === 'product') {
                $product = Product::select(['id', 'name', 'unit_cost', 'stock'])->findOrFail($data['product_id']);
                $productId = $product->id;
                $unitCost = (float) $product->unit_cost;

                if ($lotId) {
                    $lot = ProductLot::select(['id', 'unit_cost'])->find($lotId);
                    if ($lot && (float) $lot->unit_cost > 0) {
                        $unitCost = (float) $lot->unit_cost;
                    }
                }
            } else {
                $dish = Dish::select(['id', 'name', 'cost_price', 'cpv'])->findOrFail($data['dish_id']);
                $dishId = $dish->id;
                $unitCost = (float) ($dish->cost_price ?: $dish->cpv ?: 0.0);
            }

            $photoPath = null;
            if ($photo instanceof UploadedFile) {
                $photoPath = $photo->store('kitchen_wastes_photos', 'public');
            }

            $totalLossUsd = round($unitCost * $qty, 2);

            return $this->wasteRepository->create([
                'user_id' => $userId,
                'kitchen_station_id' => $data['kitchen_station_id'] ?? null,
                'item_type' => $itemType,
                'product_id' => $productId,
                'dish_id' => $dishId,
                'lot_id' => $lotId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_loss_usd' => $totalLossUsd,
                'reason' => $data['reason'] ?? KitchenWasteReasonEnum::BURNED_SPOILED->value,
                'notes' => $data['notes'] ?? null,
                'status' => KitchenWasteStatusEnum::PENDING->value,
                'photo_path' => $photoPath,
                'occurred_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Aprueba una merma pendiente: descuenta del inventario y registra el movimiento como pérdida (loss).
     */
    public function approveWaste(KitchenWaste $waste, int $approverId): KitchenWaste
    {
        $currentStatus = is_object($waste->status) ? $waste->status->value : (string) $waste->status;
        if ($currentStatus !== KitchenWasteStatusEnum::PENDING->value) {
            throw new InvalidArgumentException('Esta merma ya fue procesada anteriormente.');
        }

        return DB::transaction(function () use ($waste, $approverId) {
            $qty = (float) $waste->quantity;

            if ($waste->item_type === 'product' && $waste->product_id) {
                $product = Product::findOrFail($waste->product_id);
                $this->deductProductStockAndRecordLoss(
                    product: $product,
                    quantityToDeduct: $qty,
                    approverId: $approverId,
                    preferredLotId: $waste->lot_id,
                    dishId: null
                );
            } elseif ($waste->item_type === 'dish' && $waste->dish_id) {
                $dish = Dish::with('ingredients')->findOrFail($waste->dish_id);
                foreach ($dish->ingredients as $ingredient) {
                    $ingQty = round((float) ($ingredient->pivot->portion ?? 0) * $qty, 4);
                    if ($ingQty > 0) {
                        $this->deductProductStockAndRecordLoss(
                            product: $ingredient,
                            quantityToDeduct: $ingQty,
                            approverId: $approverId,
                            preferredLotId: null,
                            dishId: $dish->id
                        );
                    }
                }
            }

            return $this->wasteRepository->update($waste, [
                'status' => KitchenWasteStatusEnum::APPROVED->value,
                'approved_by' => $approverId,
                'approved_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Rechaza una merma pendiente sin realizar descuentos de inventario.
     */
    public function rejectWaste(KitchenWaste $waste, int $approverId, ?string $reason = null): KitchenWaste
    {
        $currentStatus = is_object($waste->status) ? $waste->status->value : (string) $waste->status;
        if ($currentStatus !== KitchenWasteStatusEnum::PENDING->value) {
            throw new InvalidArgumentException('Esta merma ya fue procesada anteriormente.');
        }

        return DB::transaction(function () use ($waste, $approverId, $reason) {
            return $this->wasteRepository->update($waste, [
                'status' => KitchenWasteStatusEnum::REJECTED->value,
                'approved_by' => $approverId,
                'approved_at' => Carbon::now(),
                'rejection_reason' => $reason,
            ]);
        });
    }

    /**
     * Descuenta stock de lotes activos (FEFO) y del producto, registrando un InventoryMovement tipo 'loss' (Pérdida).
     */
    protected function deductProductStockAndRecordLoss(
        Product $product,
        float $quantityToDeduct,
        int $approverId,
        ?int $preferredLotId = null,
        ?int $dishId = null
    ): void {
        ProductLot::where('product_id', $product->id)
            ->where('quantity', '<', 0)
            ->update(['quantity' => 0]);

        $activeLots = ProductLot::where('product_id', $product->id)
            ->where('quantity', '>', 0)
            ->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('expiration_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $hasActiveLots = $activeLots->isNotEmpty();
        $stockBefore = $hasActiveLots
            ? round((float) $activeLots->sum('quantity'), 4)
            : round((float) $product->stock, 4);

        $affectedLotId = $preferredLotId;

        ProductLotObserver::$skipMovementCreation = true;
        try {
            if ($hasActiveLots) {
                $remaining = $quantityToDeduct;

                // Si se especificó un lote preferido con saldo, descontar primero de ese lote
                if ($preferredLotId) {
                    $preferredLot = $activeLots->firstWhere('id', $preferredLotId);
                    if ($preferredLot && $preferredLot->quantity > 0) {
                        $deduct = min((float) $preferredLot->quantity, $remaining);
                        $preferredLot->quantity = round((float) $preferredLot->quantity - $deduct, 4);
                        $preferredLot->saveQuietly();
                        $remaining = round($remaining - $deduct, 4);
                        $affectedLotId = $preferredLot->id;
                    }
                }

                // Descontar el remanente por orden FEFO
                foreach ($activeLots as $lot) {
                    if ($remaining <= 0) {
                        break;
                    }
                    if ($preferredLotId && $lot->id === $preferredLotId) {
                        continue;
                    }

                    $deduct = min((float) $lot->quantity, $remaining);
                    if ($deduct > 0) {
                        $lot->quantity = round((float) $lot->quantity - $deduct, 4);
                        $lot->saveQuietly();
                        $remaining = round($remaining - $deduct, 4);
                        if (!$affectedLotId) {
                            $affectedLotId = $lot->id;
                        }
                    }
                }

                $stockAfter = round((float) ProductLot::where('product_id', $product->id)->where('quantity', '>', 0)->sum('quantity'), 4);
            } else {
                $stockAfter = max(0.0, round($stockBefore - $quantityToDeduct, 4));
            }

            $product->updateQuietly(['stock' => $stockAfter]);
            StockoutService::syncStockout($product, $stockAfter);

            InventoryMovement::create([
                'product_id' => $product->id,
                'product_lot_id' => $affectedLotId,
                'movement_type' => 'loss',
                'quantity' => -abs($quantityToDeduct),
                'dish_id' => $dishId,
                'user_id' => $approverId,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'movement_date' => Carbon::now(),
            ]);
        } finally {
            ProductLotObserver::$skipMovementCreation = false;
        }
    }
}
