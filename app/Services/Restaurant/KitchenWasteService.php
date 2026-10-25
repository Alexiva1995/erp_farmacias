<?php

namespace App\Services\Restaurant;

use App\Contracts\KitchenWasteServiceInterface;
use App\Enums\KitchenWasteReasonEnum;
use App\Models\Dish;
use App\Models\KitchenWaste;
use App\Models\ProductLot;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KitchenWasteService implements KitchenWasteServiceInterface
{
    public function listWastes(array $filters = []): array
    {
        $query = KitchenWaste::with(['user:id,username,name', 'kitchenStation', 'product:id,name', 'dish:id,name', 'lot'])
            ->latest();

        if (!empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (!empty($filters['item_type'])) {
            $query->where('item_type', $filters['item_type']);
        }

        return $query->paginate($filters['per_page'] ?? 15)->toArray();
    }

    public function recordWaste(array $data, int $userId): KitchenWaste
    {
        return DB::transaction(function () use ($data, $userId) {
            $itemType = $data['item_type']; // 'product' o 'dish'
            $qty = (float) $data['quantity'];
            if ($qty <= 0) {
                throw new InvalidArgumentException('La cantidad de merma debe ser mayor a 0.');
            }

            $unitCost = 0.0;
            $productId = null;
            $dishId = null;
            $lotId = $data['lot_id'] ?? null;

            if ($itemType === 'product') {
                $product = Product::findOrFail($data['product_id']);
                $productId = $product->id;
                $unitCost = (float) $product->unit_cost;

                // Descontar stock
                if ($lotId) {
                    $lot = ProductLot::find($lotId);
                    if ($lot) {
                        $lot->quantity = max(0, (float) $lot->quantity - $qty);
                        $lot->save();
                        $unitCost = (float) $lot->unit_cost;
                    }
                }
                $product->stock = max(0, (float) $product->stock - $qty);
                $product->save();
            } else {
                $dish = Dish::with('ingredients')->findOrFail($data['dish_id']);
                $dishId = $dish->id;
                $unitCost = (float) ($dish->cost_price ?: $dish->cpv ?: 0.0);

                // Descontar insumos de la receta
                foreach ($dish->ingredients as $ing) {
                    $ingQty = (float) $ing->pivot->portion * $qty;
                    $ing->stock = max(0, (float) $ing->stock - $ingQty);
                    $ing->save();
                }
            }

            $totalLossUsd = round($unitCost * $qty, 2);

            return KitchenWaste::create([
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
                'occurred_at' => Carbon::now(),
            ]);
        });
    }
}
