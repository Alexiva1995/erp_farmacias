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
                // Auto-crear o vincular producto de inventario para la sub-receta si no existe
                $productData = [
                    'name' => trim($dish->name),
                    'category_id' => $dish->category_id,
                    'sale_price' => (float) $dish->cost_price,
                    'stock' => 0,
                    'presentation' => 1,
                    'unit_of_measure' => 'porciones',
                    'is_active' => true,
                    'is_deleted' => false,
                    'barcode' => 'SUB-' . date('ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)),
                    'active_ingredient' => 'Sub-receta / Insumo Cocina',
                    'iva' => false,
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'unit_cost')) {
                    $productData['unit_cost'] = (float) $dish->cost_price;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'cost_price')) {
                    $productData['cost_price'] = (float) $dish->cost_price;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_sub_recipe')) {
                    $productData['is_sub_recipe'] = true;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'yield_percentage')) {
                    $productData['yield_percentage'] = 100.00;
                }

                $outputProduct = Product::create($productData);
                $dish->update(['output_product_id' => $outputProduct->id]);
                $outputProductId = $outputProduct->id;
            } else {
                $outputProduct = Product::findOrFail($outputProductId);
            }
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
            
            $lotData = [
                'product_id' => $outputProduct->id,
                'lot_number' => $lotCode,
                'quantity' => $actualQty,
                'expiration_date' => Carbon::now()->addDays($shelfLife)->toDateString(),
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('product_lots', 'unit_cost')) {
                $lotData['unit_cost'] = $unitCostCalculated;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('product_lots', 'cost_price')) {
                $lotData['cost_price'] = $unitCostCalculated;
            }

            $newLot = ProductLot::create($lotData);

            // Aumentar stock del producto elaborado
            $outputProduct->stock = (float) $outputProduct->stock + $actualQty;
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'unit_cost')) {
                $outputProduct->unit_cost = $unitCostCalculated;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'cost_price')) {
                $outputProduct->cost_price = $unitCostCalculated;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_sub_recipe')) {
                $outputProduct->is_sub_recipe = true;
            }
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

    /**
     * Crear una sub-receta (plato base status=3) y generar automáticamente su producto de inventario elaborado
     * para que pueda ser utilizado como insumo en cualquier otro plato del menú.
     */
    public function createSubRecipe(array $data): Dish
    {
        return DB::transaction(function () use ($data) {
            $yieldQty = (float) ($data['expected_yield_quantity'] ?? 1.0);
            if ($yieldQty <= 0) {
                $yieldQty = 1.0;
            }

            $unitMeasure = !empty($data['unit_of_measure']) ? trim($data['unit_of_measure']) : 'porción';
            $shelfLife = (int) ($data['shelf_life_days'] ?? 7);

            // Calcular costo total sumando los insumos de la receta + 5% por imprevisto
            $rawCost = 0.0;
            $ingredientsData = $data['ingredients'] ?? [];
            foreach ($ingredientsData as $ing) {
                $rawCost += (float) ($ing['designated_cost'] ?? 0);
            }
            $totalCost = round($rawCost * 1.05, 2);

            // Costo unitario por cada unidad/porción resultante
            $unitCost = $yieldQty > 0 ? round($totalCost / $yieldQty, 4) : $totalCost;

            // 1. Crear producto de inventario de salida marcado como sub-receta
            $productData = [
                'name' => trim($data['name']),
                'category_id' => $data['category_id'] ?? null,
                'sale_price' => $unitCost,
                'stock' => 0,
                'presentation' => 1,
                'unit_of_measure' => $unitMeasure,
                'is_active' => true,
                'is_deleted' => false,
                'barcode' => 'SUB-' . date('ymd') . '-' . strtoupper(Str::random(6)),
                'active_ingredient' => 'Sub-receta / Insumo Cocina',
                'iva' => false,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'unit_cost')) {
                $productData['unit_cost'] = $unitCost;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'cost_price')) {
                $productData['cost_price'] = $unitCost;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_sub_recipe')) {
                $productData['is_sub_recipe'] = true;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'yield_percentage')) {
                $productData['yield_percentage'] = 100.00;
            }

            $outputProduct = Product::create($productData);

            // 2. Crear el plato configurado como plato base (status=3) y vinculado a su producto de salida
            $dishAttributes = [
                'name' => trim($data['name']),
                'cost_price' => round($totalCost, 2),
                'suggested_price' => round($totalCost, 2),
                'designated_price' => round($totalCost, 2),
                'percentage_profit' => '1.00',
                'category_id' => $data['category_id'] ?? null,
                'status' => 3, // Base Dish / Sub-receta
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'cpv')) {
                $dishAttributes['cpv'] = round($totalCost, 2);
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'iva')) {
                $dishAttributes['iva'] = false;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'tax_rate')) {
                $dishAttributes['tax_rate'] = 0.00;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'preparation_time_minutes')) {
                $dishAttributes['preparation_time_minutes'] = 15;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'kitchen_station_id')) {
                $dishAttributes['kitchen_station_id'] = $data['kitchen_station_id'] ?? null;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'output_product_id')) {
                $dishAttributes['output_product_id'] = $outputProduct->id;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'expected_yield_quantity')) {
                $dishAttributes['expected_yield_quantity'] = $yieldQty;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'shelf_life_days')) {
                $dishAttributes['shelf_life_days'] = $shelfLife;
            }

            $dish = Dish::create($dishAttributes);

            // 3. Vincular los ingredientes a la sub-receta
            $hasIsRemovable = \Illuminate\Support\Facades\Schema::hasColumn('dish_ingredients', 'is_removable');
            foreach ($ingredientsData as $ing) {
                $pivotData = [
                    'portion' => $ing['portion'],
                    'designated_cost' => $ing['designated_cost'],
                ];
                if ($hasIsRemovable) {
                    $pivotData['is_removable'] = false;
                }
                $dish->ingredients()->attach($ing['product_id'], $pivotData);
            }

            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            $relations = ['ingredients', 'category'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'output_product_id')) {
                $relations[] = 'outputProduct';
            }

            return $dish->load($relations);
        });
    }
}
