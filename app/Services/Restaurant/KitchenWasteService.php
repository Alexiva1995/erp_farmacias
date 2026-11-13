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
     * Obtiene SIEMPRE el costo unificado desde la tabla products (nunca desde product_lots).
     * Si es un platillo o sub-receta, suma los costos unificados de sus ingredientes en products.
     */
    protected function resolveUnifiedUnitCost(string $itemType, ?int $productId, ?int $dishId): float
    {
        if ($itemType === 'product' && $productId) {
            $product = Product::select(['id', 'unit_cost', 'cost_price'])->findOrFail($productId);
            return round((float) ($product->unit_cost ?: $product->cost_price ?: 0.0), 4);
        }

        if ($itemType === 'dish' && $dishId) {
            $dish = Dish::with([
                'ingredients:id,name,unit_cost,cost_price',
                'outputProduct:id,name,unit_cost,cost_price',
            ])->findOrFail($dishId);

            // Si es una sub-receta vinculada a un producto de inventario con costo unificado en products
            if ($dish->outputProduct && (float) ($dish->outputProduct->unit_cost ?: $dish->outputProduct->cost_price ?: 0) > 0) {
                return round((float) ($dish->outputProduct->unit_cost ?: $dish->outputProduct->cost_price), 4);
            }

            // Calcular sumando el costo unificado de cada ingrediente desde la tabla products
            if ($dish->ingredients && $dish->ingredients->isNotEmpty()) {
                $recipeCost = 0.0;
                foreach ($dish->ingredients as $ingredient) {
                    $unifiedIngCost = (float) ($ingredient->unit_cost ?: $ingredient->cost_price ?: 0.0);
                    $portion = (float) ($ingredient->pivot->portion ?? 0.0);
                    $recipeCost += ($unifiedIngCost * $portion);
                }

                // Si es sub-receta (status = 3) con rendimiento mayor a 1, dividir entre el rendimiento esperado
                $yieldQty = (float) ($dish->expected_yield_quantity ?: 1.0);
                if ((int) $dish->status === 3 && $yieldQty > 0) {
                    $recipeCost = $recipeCost / $yieldQty;
                }

                if ($recipeCost > 0) {
                    return round($recipeCost, 4);
                }
            }

            return round((float) ($dish->cost_price ?: $dish->cpv ?: 0.0), 4);
        }

        return 0.0;
    }

    /**
     * Registra un reporte de merma utilizando exclusivamente el costo unificado de la tabla products.
     */
    public function recordWaste(array $data, int $userId, ?UploadedFile $photo = null): KitchenWaste
    {
        KitchenWaste::ensureApprovalColumns();

        return DB::transaction(function () use ($data, $userId, $photo) {
            $photoPath = null;
            if ($photo instanceof UploadedFile) {
                $photoPath = $photo->store('kitchen_wastes_photos', 'public');
            }

            return $this->createSingleWasteRecord($data, $userId, $photoPath);
        });
    }

    /**
     * Registra múltiples renglones de merma (Hoja de Merma de cierre de turno) en una sola transacción.
     *
     * @return array<int, KitchenWaste>
     */
    public function recordBatchWaste(array $items, int $userId, ?UploadedFile $photo = null, ?int $defaultStationId = null): array
    {
        KitchenWaste::ensureApprovalColumns();

        return DB::transaction(function () use ($items, $userId, $photo, $defaultStationId) {
            $photoPath = null;
            if ($photo instanceof UploadedFile) {
                $photoPath = $photo->store('kitchen_wastes_photos', 'public');
            }

            $created = [];
            foreach ($items as $itemData) {
                if (empty($itemData['kitchen_station_id']) && $defaultStationId) {
                    $itemData['kitchen_station_id'] = $defaultStationId;
                }
                $created[] = $this->createSingleWasteRecord($itemData, $userId, $photoPath);
            }

            return $created;
        });
    }

    /**
     * Lógica interna para crear un registro de merma y auto-aprobarlo si el usuario es supervisor/admin.
     */
    protected function createSingleWasteRecord(array $data, int $userId, ?string $photoPath = null): KitchenWaste
    {
        $itemType = $data['item_type'] ?? 'product';
        $qty = round((float) ($data['quantity'] ?? 0), 4);
        if ($qty <= 0) {
            throw new InvalidArgumentException('La cantidad de merma debe ser mayor a 0.');
        }

        $productId = $itemType === 'product' ? (int) ($data['product_id'] ?? 0) : null;
        $dishId = $itemType === 'dish' ? (int) ($data['dish_id'] ?? 0) : null;
        $lotId = !empty($data['lot_id']) ? (int) $data['lot_id'] : null;

        // Costo unificado estricto desde la tabla products (nunca por lote)
        $unitCost = $this->resolveUnifiedUnitCost($itemType, $productId, $dishId);
        $totalLossUsd = round($unitCost * $qty, 2);

        $user = \App\Models\User::find($userId);
        $roleName = strtolower($user?->role?->name ?? '');
        $isAuthorizedAdmin = in_array((int) ($user?->role_id ?? 0), [1, 2, 4], true)
            || str_contains($roleName, 'admin')
            || str_contains($roleName, 'supervisor');

        $autoApprove = $isAuthorizedAdmin || !empty($data['auto_approve']);

        $initialStatus = $autoApprove
            ? KitchenWasteStatusEnum::APPROVED->value
            : KitchenWasteStatusEnum::PENDING->value;

        $waste = $this->wasteRepository->create([
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
            'status' => $initialStatus,
            'photo_path' => $photoPath,
            'approved_by' => $autoApprove ? $userId : null,
            'approved_at' => $autoApprove ? Carbon::now() : null,
            'occurred_at' => Carbon::now(),
        ]);

        if ($autoApprove) {
            $this->executeInventoryDeduction($waste, $userId);
        }

        return $waste->refresh()->load([
            'user:id,username',
            'approver:id,username',
            'kitchenStation:id,name',
            'product:id,name,stock,unit_of_measure,unit_cost',
            'dish:id,name,status,cost_price,cpv,output_product_id,expected_yield_quantity',
            'dish.ingredients:id,name,stock,unit_of_measure,unit_cost',
            'lot:id,product_id,lot_number,quantity',
        ]);
    }

    /**
     * Aprueba una merma pendiente: sincroniza el costo unificado de products, descuenta inventario y registra pérdida.
     */
    public function approveWaste(KitchenWaste $waste, int $approverId): KitchenWaste
    {
        $currentStatus = is_object($waste->status) ? $waste->status->value : (string) $waste->status;
        if ($currentStatus !== KitchenWasteStatusEnum::PENDING->value) {
            throw new InvalidArgumentException('Esta merma ya fue procesada anteriormente.');
        }

        return DB::transaction(function () use ($waste, $approverId) {
            $qty = round((float) $waste->quantity, 4);

            // Re-sincronizar costo unificado de la tabla products al momento de aprobar
            $unifiedUnitCost = $this->resolveUnifiedUnitCost(
                (string) $waste->item_type,
                $waste->product_id ? (int) $waste->product_id : null,
                $waste->dish_id ? (int) $waste->dish_id : null
            );
            $totalLossUsd = round($unifiedUnitCost * $qty, 2);

            $this->executeInventoryDeduction($waste, $approverId);

            return $this->wasteRepository->update($waste, [
                'unit_cost' => $unifiedUnitCost,
                'total_loss_usd' => $totalLossUsd,
                'status' => KitchenWasteStatusEnum::APPROVED->value,
                'approved_by' => $approverId,
                'approved_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Ejecuta el descuento físico en inventario según si el ítem es un producto directo, sub-receta o platillo.
     */
    protected function executeInventoryDeduction(KitchenWaste $waste, int $approverId): void
    {
        $qty = round((float) $waste->quantity, 4);

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
            $dish = Dish::with(['ingredients', 'outputProduct'])->findOrFail($waste->dish_id);

            // Si es una sub-receta que ya fue producida en inventario (tiene outputProduct con stock disponible)
            if ($dish->outputProduct && (float) $dish->outputProduct->stock >= $qty) {
                $this->deductProductStockAndRecordLoss(
                    product: $dish->outputProduct,
                    quantityToDeduct: $qty,
                    approverId: $approverId,
                    preferredLotId: $waste->lot_id,
                    dishId: $dish->id
                );
                return;
            }

            // En platillos terminados (o sub-recetas sin stock pre-elaborado), descontar cada insumo del escandallo
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
