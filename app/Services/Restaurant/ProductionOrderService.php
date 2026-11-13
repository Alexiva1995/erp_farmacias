<?php

namespace App\Services\Restaurant;

use App\Contracts\ProductionOrderServiceInterface;
use App\Enums\ProductionOrderStatusEnum;
use App\Http\Resources\Restaurant\ProductionOrderResource;
use App\Models\Dish;
use App\Models\InventoryMovement;
use App\Models\ProductLot;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Observers\ProductLotObserver;
use App\Services\Inventory\StockoutService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductionOrderService implements ProductionOrderServiceInterface
{
    public function listOrders(array $filters = []): array
    {
        $query = ProductionOrder::with([
            'dish:id,name,expected_yield_quantity,shelf_life_days',
            'outputProduct:id,name,unit_of_measure,stock,unit_cost',
            'user:id,username',
            'kitchenStation:id,name',
            'generatedLot:id,product_id,lot_number,quantity,expiration_date',
        ]);

        if (!empty($filters['q'])) {
            $search = trim((string) $filters['q']);
            $query->where(function ($sub) use ($search) {
                $sub->where('code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('dish', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('generatedLot', fn ($q) => $q->where('lot_number', 'like', "%{$search}%"));
            });
        }

        if (!empty($filters['dish_id'])) {
            $query->where('dish_id', (int) $filters['dish_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $allowedSorts = ['id', 'code', 'batch_multiplier', 'actual_quantity', 'total_production_cost', 'produced_at', 'status'];
        $sortBy = in_array($filters['sortBy'] ?? '', $allowedSorts, true) ? $filters['sortBy'] : 'id';
        $orderBy = strtolower((string) ($filters['orderBy'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $orderBy);

        $perPage = (int) ($filters['per_page'] ?? $filters['itemsPerPage'] ?? 15);
        $paginator = $query->paginate($perPage > 0 ? $perPage : 15);

        return [
            'data' => ProductionOrderResource::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
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
                    'barcode' => 'SUB-' . date('ymd') . '-' . strtoupper(Str::random(6)),
                    'active_ingredient' => 'Sub-receta / Insumo Cocina',
                    'iva' => false,
                    'unit_cost' => (float) $dish->cost_price,
                    'is_sub_recipe' => true,
                    'yield_percentage' => 100.00,
                ];

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
            if ($actualQty <= 0) {
                $actualQty = $expectedQty;
            }

            $totalProductionCost = 0.0;

            ProductLotObserver::$skipMovementCreation = true;
            try {
                // Descontar cada ingrediente aplicando rendimiento (yield_percentage) y el costo unificado de la tabla products
                foreach ($dish->ingredients as $ingredient) {
                    $portionNeeded = (float) $ingredient->pivot->portion * $multiplier;
                    $yieldPct = (float) ($ingredient->yield_percentage > 0 ? $ingredient->yield_percentage : 100.0);
                    $grossQuantityToDeduct = $portionNeeded / ($yieldPct / 100.0);

                    $presentation = (float) ($ingredient->presentation ?? 1.0);
                    $safePresentation = $presentation > 0 ? $presentation : 1.0;
                    // Convertir la porción bruta en unidades de receta a unidades/paquetes de inventario
                    $packsToDeduct = round($grossQuantityToDeduct / $safePresentation, 4);

                    // Costo SIEMPRE desde la tabla products (unit_cost promediado del sistema, nunca desde product_lots)
                    $unifiedIngCost = (float) ($ingredient->unit_cost ?: 0.0);
                    $totalProductionCost += ($packsToDeduct * $unifiedIngCost);

                    $stockBefore = round((float) $ingredient->stock, 4);
                    $affectedLotId = null;

                    // Descuento FEFO sobre los lotes del ingrediente
                    $remainingToDeduct = $packsToDeduct;
                    $lots = ProductLot::where('product_id', $ingredient->id)
                        ->where('quantity', '>', 0)
                        ->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END ASC')
                        ->orderBy('expiration_date', 'asc')
                        ->lockForUpdate()
                        ->get();

                    foreach ($lots as $lot) {
                        if ($remainingToDeduct <= 0) {
                            break;
                        }
                        $deductFromLot = min((float) $lot->quantity, $remainingToDeduct);
                        if ($deductFromLot > 0) {
                            $lot->quantity = round((float) $lot->quantity - $deductFromLot, 4);
                            $lot->saveQuietly();
                            $remainingToDeduct = round($remainingToDeduct - $deductFromLot, 4);
                            if (!$affectedLotId) {
                                $affectedLotId = $lot->id;
                            }
                        }
                    }

                    // Descontar stock general del producto ingrediente
                    $stockAfter = max(0.0, round($stockBefore - $packsToDeduct, 4));
                    $ingredient->updateQuietly(['stock' => $stockAfter]);
                    StockoutService::syncStockout($ingredient, $stockAfter);

                    // Registrar salida de materia prima en Kardex (InventoryMovement)
                    if ($packsToDeduct > 0) {
                        InventoryMovement::create([
                            'product_id' => $ingredient->id,
                            'product_lot_id' => $affectedLotId,
                            'movement_type' => 'adjustment',
                            'quantity' => -abs($packsToDeduct),
                            'dish_id' => $dish->id,
                            'user_id' => $userId,
                            'stock_before' => $stockBefore,
                            'stock_after' => $stockAfter,
                            'movement_date' => Carbon::now(),
                        ]);
                    }
                }

                // Sumar 5% de imprevisto al costo de los ingredientes de la tabla products
                if ($totalProductionCost > 0) {
                    $totalProductionCost = round($totalProductionCost * 1.05, 4);
                } else {
                    // Fallback al costo de la receta (que ya incluye su 5%)
                    $totalProductionCost = round((float) $dish->cost_price * $multiplier, 4);
                }

                $unitCostCalculated = $actualQty > 0
                    ? round($totalProductionCost / $actualQty, 4)
                    : round((float) $dish->cost_price / ($dish->expected_yield_quantity ?: 1.0), 4);

                // Generar nuevo Lote para el producto resultante elaborado
                $shelfLife = (int) ($dish->shelf_life_days ?: 7);
                $lotCode = 'SUB-' . date('Ymd') . '-' . strtoupper(Str::random(4));

                // Sanear saldo negativo previo en lotes de la sub-receta
                ProductLot::where('product_id', $outputProduct->id)
                    ->where('quantity', '<', 0)
                    ->update(['quantity' => 0]);

                $outputStockBefore = max(0.0, round((float) $outputProduct->stock, 4));

                $newLot = new ProductLot([
                    'product_id' => $outputProduct->id,
                    'lot_number' => $lotCode,
                    'quantity' => $actualQty,
                    'expiration_date' => Carbon::now()->addDays($shelfLife)->toDateString(),
                    'unit_cost' => $unitCostCalculated,
                ]);
                $newLot->saveQuietly();

                // Aumentar stock del producto elaborado y actualizar su costo en la tabla products
                $outputStockAfter = round($outputStockBefore + $actualQty, 4);
                $outputProduct->updateQuietly([
                    'stock' => $outputStockAfter,
                    'unit_cost' => $unitCostCalculated,
                    'is_sub_recipe' => true,
                ]);
                StockoutService::syncStockout($outputProduct, $outputStockAfter);

                // Registrar ingreso del lote elaborado en Kardex (InventoryMovement)
                InventoryMovement::create([
                    'product_id' => $outputProduct->id,
                    'product_lot_id' => $newLot->id,
                    'movement_type' => 'adjustment',
                    'quantity' => $actualQty,
                    'dish_id' => $dish->id,
                    'user_id' => $userId,
                    'stock_before' => $outputStockBefore,
                    'stock_after' => $outputStockAfter,
                    'movement_date' => Carbon::now(),
                ]);
            } finally {
                ProductLotObserver::$skipMovementCreation = false;
            }

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

            return $prodOrder->load([
                'dish:id,name,expected_yield_quantity,shelf_life_days',
                'outputProduct:id,name,unit_of_measure,stock,unit_cost',
                'user:id,username',
                'kitchenStation:id,name',
                'generatedLot:id,product_id,lot_number,quantity,expiration_date',
            ]);
        });
    }

    public function cancelOrder(ProductionOrder $productionOrder): bool
    {
        return DB::transaction(function () use ($productionOrder) {
            if ($productionOrder->status === ProductionOrderStatusEnum::CANCELLED) {
                return true;
            }

            $productionOrder->load(['dish.ingredients', 'generatedLot', 'outputProduct']);
            $userId = (int) (Auth::id() ?: $productionOrder->user_id ?: 1);
            $multiplier = (float) ($productionOrder->batch_multiplier ?: 1.0);

            ProductLotObserver::$skipMovementCreation = true;
            try {
                // 1. Retirar el lote generado y descontar el stock del producto elaborado
                $product = $productionOrder->outputProduct;
                $lot = $productionOrder->generatedLot;
                $qtyToRemove = (float) ($lot?->quantity ?? $productionOrder->actual_quantity ?? 0);

                if ($product && $qtyToRemove > 0) {
                    $stockBefore = round((float) $product->stock, 4);
                    $stockAfter = max(0.0, round($stockBefore - $qtyToRemove, 4));
                    $product->updateQuietly(['stock' => $stockAfter]);
                    StockoutService::syncStockout($product, $stockAfter);

                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'product_lot_id' => $lot?->id,
                        'movement_type' => 'adjustment',
                        'quantity' => -abs($qtyToRemove),
                        'dish_id' => $productionOrder->dish_id,
                        'user_id' => $userId,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'movement_date' => Carbon::now(),
                    ]);
                }

                if ($lot) {
                    $lot->deleteQuietly();
                }

                // 2. Reintegrar al inventario los ingredientes consumidos en la orden de producción
                if ($productionOrder->dish && $productionOrder->dish->ingredients) {
                    foreach ($productionOrder->dish->ingredients as $ingredient) {
                        $portionNeeded = (float) $ingredient->pivot->portion * $multiplier;
                        $yieldPct = (float) ($ingredient->yield_percentage > 0 ? $ingredient->yield_percentage : 100.0);
                        $grossQuantityToRestore = $portionNeeded / ($yieldPct / 100.0);

                        $presentation = (float) ($ingredient->presentation ?? 1.0);
                        $safePresentation = $presentation > 0 ? $presentation : 1.0;
                        $packsToRestore = round($grossQuantityToRestore / $safePresentation, 4);

                        if ($packsToRestore <= 0) {
                            continue;
                        }

                        $ingStockBefore = round((float) $ingredient->stock, 4);
                        $targetLot = ProductLot::where('product_id', $ingredient->id)
                            ->orderBy('expiration_date', 'desc')
                            ->orderBy('id', 'desc')
                            ->first();

                        if ($targetLot) {
                            $targetLot->quantity = round(max(0.0, (float) $targetLot->quantity) + $packsToRestore, 4);
                            $targetLot->saveQuietly();
                        }

                        $ingStockAfter = round($ingStockBefore + $packsToRestore, 4);
                        $ingredient->updateQuietly(['stock' => $ingStockAfter]);
                        StockoutService::syncStockout($ingredient, $ingStockAfter);

                        InventoryMovement::create([
                            'product_id' => $ingredient->id,
                            'product_lot_id' => $targetLot?->id,
                            'movement_type' => 'adjustment',
                            'quantity' => $packsToRestore,
                            'dish_id' => $productionOrder->dish_id,
                            'user_id' => $userId,
                            'stock_before' => $ingStockBefore,
                            'stock_after' => $ingStockAfter,
                            'movement_date' => Carbon::now(),
                        ]);
                    }
                }
            } finally {
                ProductLotObserver::$skipMovementCreation = false;
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
        Dish::ensureRestaurantSchema();

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
        $conn = $subRecipe->getConnection();
        Dish::ensureRestaurantSchema($conn);

        return $conn->transaction(function () use ($subRecipe, $data) {

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
            $ingredientIds = array_map(fn ($ing) => (int) ($ing['product_id'] ?? 0), $ingredientsData);
            $outputProductId = $subRecipe->output_product_id;

            // Si output_product_id apunta por error a uno de los insumos base de la receta, desvincularlo
            if (!empty($outputProductId) && in_array((int) $outputProductId, $ingredientIds, true)) {
                $outputProductId = null;
            }

            if (empty($outputProductId)) {
                $existingProd = Product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $subRecipe->name))])
                    ->where('is_sub_recipe', true)
                    ->whereNotIn('id', $ingredientIds)
                    ->first();
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
