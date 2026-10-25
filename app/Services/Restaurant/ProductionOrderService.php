<?php

namespace App\Services\Restaurant;

use App\Contracts\ProductionOrderServiceInterface;
use App\Enums\ProductionOrderStatusEnum;
use App\Models\Dish;
use App\Models\ProductLot;
use App\Models\Product;
use App\Models\ProductionOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductionOrderService implements ProductionOrderServiceInterface
{
    public function listOrders(array $filters = []): array
    {
        $query = ProductionOrder::with(['dish', 'outputProduct', 'user:id,username,name', 'kitchenStation', 'generatedLot'])
            ->latest();

        if (!empty($filters['dish_id'])) {
            $query->where('dish_id', $filters['dish_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15)->toArray();
    }

    public function createAndExecute(array $data, int $userId): ProductionOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $dish = Dish::with('ingredients')->findOrFail($data['dish_id']);

            $outputProductId = $data['output_product_id'] ?? $dish->output_product_id;
            if (!$outputProductId) {
                throw new InvalidArgumentException('La receta debe tener un producto de inventario de salida asignado.');
            }

            $outputProduct = Product::findOrFail($outputProductId);
            $multiplier = (float) ($data['batch_multiplier'] ?? 1.0);
            if ($multiplier <= 0) {
                throw new InvalidArgumentException('El multiplicador de lote debe ser mayor a cero.');
            }

            $expectedQty = (float) ($dish->expected_yield_quantity ?: 1.0) * $multiplier;
            $actualQty = (float) ($data['actual_quantity'] ?? $expectedQty);

            $totalProductionCost = 0.0;

            // Descontar cada ingrediente aplicando rendimiento (yield_percentage)
            foreach ($dish->ingredients as $ingredient) {
                $portionNeeded = (float) $ingredient->pivot->portion * $multiplier;
                $yieldPct = (float) ($ingredient->yield_percentage > 0 ? $ingredient->yield_percentage : 100.0);
                $grossQuantityToDeduct = $portionNeeded / ($yieldPct / 100.0);

                // Descuento FEFO sobre los lotes del ingrediente
                $remainingToDeduct = $grossQuantityToDeduct;
                $lots = ProductLot::where('product_id', $ingredient->id)
                    ->where('quantity', '>', 0)
                    ->orderBy('expiration_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($remainingToDeduct <= 0) break;
                    $deductFromLot = min($lot->quantity, $remainingToDeduct);
                    $lot->quantity -= $deductFromLot;
                    $lot->save();

                    $remainingToDeduct -= $deductFromLot;
                    $totalProductionCost += ($deductFromLot * (float) ($lot->unit_cost ?: $ingredient->unit_cost));
                }

                // Descontar stock general del producto ingrediente
                $ingredient->stock = max(0, $ingredient->stock - $grossQuantityToDeduct);
                $ingredient->save();
            }

            $unitCostCalculated = $actualQty > 0 ? round($totalProductionCost / $actualQty, 4) : 0.0;

            // Generar nuevo Lote para el producto resultante elaborado
            $shelfLife = $dish->shelf_life_days ?: 7;
            $lotCode = 'SUB-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            
            $newLot = ProductLot::create([
                'product_id' => $outputProduct->id,
                'lot_number' => $lotCode,
                'quantity' => $actualQty,
                'unit_cost' => $unitCostCalculated,
                'expiration_date' => Carbon::now()->addDays($shelfLife)->toDateString(),
            ]);

            // Aumentar stock del producto elaborado
            $outputProduct->stock = (float) $outputProduct->stock + $actualQty;
            $outputProduct->unit_cost = $unitCostCalculated;
            $outputProduct->is_sub_recipe = true;
            $outputProduct->save();

            // Registrar Orden de Producción
            $prodOrder = ProductionOrder::create([
                'code' => 'PROD-' . strtoupper(Str::random(6)),
                'dish_id' => $dish->id,
                'output_product_id' => $outputProduct->id,
                'user_id' => $userId,
                'kitchen_station_id' => $data['kitchen_station_id'] ?? $dish->kitchen_station_id,
                'batch_multiplier' => $multiplier,
                'expected_quantity' => $expectedQty,
                'actual_quantity' => $actualQty,
                'total_production_cost' => $totalProductionCost,
                'unit_cost_calculated' => $unitCostCalculated,
                'generated_lot_id' => $newLot->id,
                'status' => ProductionOrderStatusEnum::COMPLETED,
                'notes' => $data['notes'] ?? null,
                'produced_at' => Carbon::now(),
            ]);

            return $prodOrder;
        });
    }

    public function cancelOrder(ProductionOrder $productionOrder): bool
    {
        return DB::transaction(function () use ($productionOrder) {
            if ($productionOrder->status === ProductionOrderStatusEnum::CANCELLED) {
                return true;
            }

            // Si tenía un lote generado, retirar ese stock
            if ($productionOrder->generatedLot) {
                $lot = $productionOrder->generatedLot;
                $product = $productionOrder->outputProduct;
                if ($product) {
                    $product->stock = max(0, (float) $product->stock - (float) $lot->quantity);
                    $product->save();
                }
                $lot->delete();
            }

            $productionOrder->update(['status' => ProductionOrderStatusEnum::CANCELLED]);
            return true;
        });
    }
}
