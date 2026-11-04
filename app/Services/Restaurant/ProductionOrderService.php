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

                $productData['unit_cost'] = (float) $dish->cost_price;
                $productData['is_sub_recipe'] = true;
                $productData['yield_percentage'] = 100.00;

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
                    $lotCost = (float) ($lot->unit_cost ?: ($ingredient->unit_cost ?: 0));
                    $totalProductionCost += ($deductFromLot * $lotCost);
                }

                // Si queda cantidad por descontar (sin lotes suficientes), calcular el costo con el unit_cost de inventario
                if ($remainingToDeduct > 0) {
                    $totalProductionCost += ($remainingToDeduct * (float) ($ingredient->unit_cost ?: 0));
                }

                // Descontar stock general del producto ingrediente
                $ingredient->stock = max(0, $ingredient->stock - $grossQuantityToDeduct);
                $ingredient->save();
            }

            // Sumar 5% de imprevisto al costo de producción
            $totalProductionCost = round($totalProductionCost * 1.05, 4);

            // Si por alguna razón el costo calculado es 0, usar el costo de la receta
            if ($totalProductionCost <= 0) {
                $totalProductionCost = round((float) $dish->cost_price * $multiplier, 4);
            }

            $unitCostCalculated = $actualQty > 0 ? round($totalProductionCost / $actualQty, 4) : round((float) $dish->cost_price / ($dish->expected_yield_quantity ?: 1.0), 4);

            // Generar nuevo Lote para el producto resultante elaborado
            $shelfLife = $dish->shelf_life_days ?: 7;
            $lotCode = 'SUB-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            
            $lotData = [
                'product_id' => $outputProduct->id,
                'lot_number' => $lotCode,
                'quantity' => $actualQty,
                'expiration_date' => Carbon::now()->addDays($shelfLife)->toDateString(),
            ];
            $lotData['unit_cost'] = $unitCostCalculated;

            $newLot = ProductLot::create($lotData);

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

    /**
     * Crear una sub-receta (plato base status=3) y generar automáticamente su producto de inventario elaborado
     * para que pueda ser utilizado como insumo en cualquier otro plato del menú.
     */
    public function createSubRecipe(array $data): Dish
    {
        return DB::transaction(function () use ($data) {
            Dish::ensureRestaurantSchema();

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

            $productData['unit_cost'] = $unitCost;
            $productData['is_sub_recipe'] = true;
            $productData['yield_percentage'] = 100.00;

            $outputProduct = Product::create($productData);

            // 2. Crear el plato configurado como plato base (status=3) y vinculado a su producto de salida
            $conn = (new Dish())->getConnection();
            $schema = $conn->getSchemaBuilder();

            $dishAttributes = [
                'name' => trim($data['name']),
                'cost_price' => round($totalCost, 2),
                'suggested_price' => round($totalCost, 2),
                'designated_price' => round($totalCost, 2),
                'percentage_profit' => '1.00',
                'status' => 3, // Base Dish / Sub-receta
                'output_product_id' => $outputProduct->id,
                'expected_yield_quantity' => $yieldQty,
                'shelf_life_days' => $shelfLife,
            ];

            if ($schema->hasColumn('dishes', 'category_id')) {
                $dishAttributes['category_id'] = $data['category_id'] ?? null;
            }
            if ($schema->hasColumn('dishes', 'cpv')) {
                $dishAttributes['cpv'] = round($totalCost, 2);
            }
            if ($schema->hasColumn('dishes', 'iva')) {
                $dishAttributes['iva'] = false;
            }
            if ($schema->hasColumn('dishes', 'tax_rate')) {
                $dishAttributes['tax_rate'] = 0.00;
            }
            if ($schema->hasColumn('dishes', 'preparation_time_minutes')) {
                $dishAttributes['preparation_time_minutes'] = 15;
            }
            if ($schema->hasColumn('dishes', 'kitchen_station_id')) {
                $dishAttributes['kitchen_station_id'] = $data['kitchen_station_id'] ?? null;
            }

            $dish = Dish::create($dishAttributes);

            // 3. Vincular los ingredientes a la sub-receta
            $conn = $dish->getConnection();
            $hasRemovable = false;
            try {
                $hasRemovable = $conn->getSchemaBuilder()->hasColumn('dish_ingredients', 'is_removable');
            } catch (\Throwable $e) {}

            foreach ($ingredientsData as $ing) {
                $pivotData = [
                    'portion' => $ing['portion'],
                    'designated_cost' => $ing['designated_cost'],
                ];
                if ($hasRemovable) {
                    $pivotData['is_removable'] = false;
                }
                $dish->ingredients()->attach($ing['product_id'], $pivotData);
            }

            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            $relations = ['ingredients', 'category', 'outputProduct'];

            return $dish->load($relations);
        });
    }

    /**
     * Actualizar una sub-receta existente, recalcular su costo unitario por rendimiento y propagarlo a los platos.
     */
    public function updateSubRecipe(Dish $subRecipe, array $data): Dish
    {
        return DB::transaction(function () use ($subRecipe, $data) {
            Dish::ensureRestaurantSchema($subRecipe->getConnection());

            $yieldQty = (float) ($data['expected_yield_quantity'] ?? $subRecipe->expected_yield_quantity ?? 1.0);
            if ($yieldQty <= 0) {
                $yieldQty = 1.0;
            }

            $unitMeasure = !empty($data['unit_of_measure'])
                ? trim((string) $data['unit_of_measure'])
                : ($subRecipe->outputProduct?->unit_of_measure ?: 'porción');
            $shelfLife = (int) ($data['shelf_life_days'] ?? $subRecipe->shelf_life_days ?? 7);

            // Calcular costo total sumando los insumos de la receta + 5% por imprevisto
            $rawCost = 0.0;
            $ingredientsData = $data['ingredients'] ?? [];
            foreach ($ingredientsData as $ing) {
                $rawCost += (float) ($ing['designated_cost'] ?? 0);
            }
            $totalCost = round($rawCost * 1.05, 2);
            $unitCost = $yieldQty > 0 ? round($totalCost / $yieldQty, 4) : $totalCost;

            // 1. Vincular o actualizar el producto de inventario generado por la sub-receta
            $outputProductId = $subRecipe->output_product_id;
            if (empty($outputProductId)) {
                $existingProd = Product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $subRecipe->name))])->first();
                if ($existingProd) {
                    $outputProductId = $existingProd->id;
                }
            }

            if (empty($outputProductId)) {
                $outputProduct = Product::create([
                    'name' => trim($data['name']),
                    'category_id' => $data['category_id'] ?? $subRecipe->category_id,
                    'sale_price' => $unitCost,
                    'unit_cost' => $unitCost,
                    'stock' => 0,
                    'presentation' => 1,
                    'unit_of_measure' => $unitMeasure,
                    'is_active' => true,
                    'is_deleted' => false,
                    'barcode' => 'SUB-' . date('ymd') . '-' . strtoupper(Str::random(6)),
                    'active_ingredient' => 'Sub-receta / Insumo Cocina',
                    'iva' => false,
                    'is_sub_recipe' => true,
                    'yield_percentage' => 100.00,
                ]);
                $outputProductId = $outputProduct->id;
            } else {
                Product::withoutEvents(function () use ($outputProductId, $data, $subRecipe, $unitCost, $unitMeasure) {
                    Product::where('id', $outputProductId)->update([
                        'name' => trim($data['name']),
                        'category_id' => $data['category_id'] ?? $subRecipe->category_id,
                        'unit_cost' => $unitCost,
                        'sale_price' => $unitCost,
                        'unit_of_measure' => $unitMeasure,
                        'is_sub_recipe' => true,
                    ]);
                });
            }

            // 2. Actualizar la sub-receta
            $conn = $subRecipe->getConnection();
            $schema = $conn->getSchemaBuilder();

            $dishUpdate = [
                'name' => trim($data['name']),
                'cost_price' => $totalCost,
                'suggested_price' => $totalCost,
                'designated_price' => $totalCost,
                'status' => 3,
                'output_product_id' => $outputProductId,
                'expected_yield_quantity' => $yieldQty,
                'shelf_life_days' => $shelfLife,
            ];

            if ($schema->hasColumn('dishes', 'cpv')) {
                $dishUpdate['cpv'] = $totalCost;
            }
            if ($schema->hasColumn('dishes', 'category_id')) {
                $dishUpdate['category_id'] = $data['category_id'] ?? $subRecipe->category_id;
            }
            if ($schema->hasColumn('dishes', 'kitchen_station_id')) {
                $dishUpdate['kitchen_station_id'] = $data['kitchen_station_id'] ?? $subRecipe->kitchen_station_id;
            }

            $subRecipe->update($dishUpdate);

            // 3. Sincronizar ingredientes
            $conn = $subRecipe->getConnection();
            $hasRemovable = false;
            try {
                $hasRemovable = $conn->getSchemaBuilder()->hasColumn('dish_ingredients', 'is_removable');
            } catch (\Throwable $e) {}

            $syncData = [];
            foreach ($ingredientsData as $ing) {
                $pivotData = [
                    'portion' => $ing['portion'],
                    'designated_cost' => $ing['designated_cost'],
                ];
                if ($hasRemovable) {
                    $pivotData['is_removable'] = false;
                }
                $syncData[$ing['product_id']] = $pivotData;
            }
            $subRecipe->ingredients()->sync($syncData);

            // 4. Propagar el nuevo costo unitario a todos los platos del menú que usen esta sub-receta
            try {
                app(\App\Services\DishServices::class)->syncCostsAndFlagUnprofitableDishes();
            } catch (\Throwable $e) {
                \Log::warning('Error sincronizando costos de platos tras editar sub-receta: ' . $e->getMessage());
            }

            \Cache::forget('resources.categories.dishes');

            return $subRecipe->fresh(['ingredients', 'category', 'outputProduct']);
        });
    }
}
