<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Dish as DishContract;
use App\Models\Dish;
use App\Models\Product;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DishServices implements DishContract
{
    /**
     * Obtener el constructor de esquema de la conexión activa del modelo Dish y asegurar las columnas de restaurante.
     */
    private function schema(): SchemaBuilder
    {
        $conn = (new Dish())->getConnection();
        Dish::ensureRestaurantSchema($conn);

        return $conn->getSchemaBuilder();
    }

    /**
     * Construir arreglo de relaciones disponibles según el esquema de base de datos.
     *
     * @return array<int, string>
     */
    private function getAvailableRelations(): array
    {
        $schema = $this->schema();
        $relations = ['category', 'ingredients'];

        if ($schema->hasTable('modifier_groups') && $schema->hasTable('dish_modifier_group')) {
            $relations[] = 'modifierGroups.options.product';
        }

        if ($schema->hasTable('kitchen_stations') && $schema->hasColumn('dishes', 'kitchen_station_id')) {
            $relations[] = 'kitchenStation';
        }

        if ($schema->hasColumn('dishes', 'output_product_id')) {
            $relations[] = 'outputProduct';
        }

        return $relations;
    }

    /**
     * Sincronizar costos actuales de los insumos de inventario en las recetas de los platos.
     * Si el precio de venta (designated_price) es menor al precio sugerido, pasa automáticamente a 'En Revisión' (status = 2).
     */
    public function syncCostsAndFlagUnprofitableDishes(): array
    {
        $schema = $this->schema();
        $hasCpvColumn = $schema->hasColumn('dishes', 'cpv');
        $dishSelectColumns = [
            'id',
            'name',
            'cost_price',
            'suggested_price',
            'designated_price',
            'percentage_profit',
            'status',
        ];
        if ($hasCpvColumn) {
            $dishSelectColumns[] = 'cpv';
        }
        if ($schema->hasColumn('dishes', 'output_product_id')) {
            $dishSelectColumns[] = 'output_product_id';
        }
        if ($schema->hasColumn('dishes', 'expected_yield_quantity')) {
            $dishSelectColumns[] = 'expected_yield_quantity';
        }

        $ingredientsRelation = [
            'ingredients' => function ($query) {
                $query->select([
                    'products.id',
                    'products.name',
                    'products.unit_cost',
                    'products.presentation',
                ]);
            },
        ];

        $updatedCount = 0;
        $flaggedUnderReviewCount = 0;

        // Paso 1: Actualizar primero las sub-recetas / preparaciones base (status = 3)
        $subRecipes = Dish::select($dishSelectColumns)
            ->where('status', 3)
            ->with($ingredientsRelation)
            ->get();

        foreach ($subRecipes as $subRecipe) {
            $currentCost = 0.0;

            foreach ($subRecipe->ingredients as $ingredient) {
                // Evitar ciclo de auto-referencia si la sub-receta se tiene a sí misma como ingrediente
                if (!empty($subRecipe->output_product_id) && (int) $ingredient->id === (int) $subRecipe->output_product_id) {
                    $rawAlternative = Product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $subRecipe->name))])
                        ->where('id', '!=', $subRecipe->output_product_id)
                        ->where(function ($q) {
                            $q->where('is_sub_recipe', false)->orWhereNull('is_sub_recipe');
                        })
                        ->first();

                    if ($rawAlternative) {
                        DB::table('dish_ingredients')
                            ->where('dish_id', $subRecipe->id)
                            ->where('product_id', $ingredient->id)
                            ->update(['product_id' => $rawAlternative->id]);
                        $ingredient = $rawAlternative;
                    } else {
                        continue;
                    }
                }

                $portion = (float) ($ingredient->pivot?->portion ?? 0);
                $unitCost = (float) ($ingredient->unit_cost ?? 0);
                $presentation = (float) ($ingredient->presentation ?? 1);
                $safePresentation = $presentation > 0 ? $presentation : 1.0;

                $freshDesignatedCost = round(($portion * $unitCost) / $safePresentation, 4);

                DB::table('dish_ingredients')
                    ->where('dish_id', $subRecipe->id)
                    ->where('product_id', $ingredient->id)
                    ->update(['designated_cost' => $freshDesignatedCost]);

                $currentCost += $freshDesignatedCost;
            }

            $currentCost = round($currentCost * 1.05, 2);

            $updateFields = [];
            if (abs((float) $subRecipe->cost_price - $currentCost) > 0.001) {
                $updateFields['cost_price'] = $currentCost;
                $updateFields['suggested_price'] = $currentCost;
                if ($hasCpvColumn) {
                    $updateFields['cpv'] = $currentCost;
                }
            }

            if (!empty($updateFields)) {
                $subRecipe->update($updateFields);
                $updatedCount++;
            }

            // Actualizar el costo unitario del producto generado por la sub-receta
            $yield = (float) ($subRecipe->expected_yield_quantity ?? 1);
            $unitCostProduct = $yield > 0 ? round($currentCost / $yield, 4) : $currentCost;
            $ingredientIds = $subRecipe->ingredients->pluck('id')->map(fn ($id) => (int) $id)->toArray();

            if (empty($subRecipe->output_product_id) && $schema->hasColumn('dishes', 'output_product_id')) {
                $linkedProd = Product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $subRecipe->name))])
                    ->where('is_sub_recipe', true)
                    ->whereNotIn('id', $ingredientIds)
                    ->first();
                if ($linkedProd) {
                    $subRecipe->update(['output_product_id' => $linkedProd->id]);
                    $subRecipe->output_product_id = $linkedProd->id;
                }
            }

            // Nunca sobreescribir el costo si el output_product_id apunta por error a uno de sus propios insumos base
            if (!empty($subRecipe->output_product_id) && !in_array((int) $subRecipe->output_product_id, $ingredientIds, true)) {
                Product::withoutEvents(function () use ($subRecipe, $unitCostProduct) {
                    Product::where('id', $subRecipe->output_product_id)->update([
                        'unit_cost' => $unitCostProduct,
                        'sale_price' => $unitCostProduct,
                    ]);
                });
            }
        }

        // Paso 2: Actualizar platos de menú (status != 3) con los costos frescos (incluyendo sub-recetas actualizadas)
        $dishes = Dish::select($dishSelectColumns)
            ->where('status', '!=', 3)
            ->with($ingredientsRelation)
            ->get();

        foreach ($dishes as $dish) {
            $currentDishCost = 0.0;

            foreach ($dish->ingredients as $ingredient) {
                $portion = (float) ($ingredient->pivot?->portion ?? 0);
                $unitCost = (float) ($ingredient->unit_cost ?? 0);
                $presentation = (float) ($ingredient->presentation ?? 1);
                $safePresentation = $presentation > 0 ? $presentation : 1.0;

                $freshDesignatedCost = round(($portion * $unitCost) / $safePresentation, 4);

                DB::table('dish_ingredients')
                    ->where('dish_id', $dish->id)
                    ->where('product_id', $ingredient->id)
                    ->update(['designated_cost' => $freshDesignatedCost]);

                $currentDishCost += $freshDesignatedCost;
            }

            // Sumar 5% del costo por Imprevisto
            $currentDishCost = round($currentDishCost * 1.05, 2);
            $profitMultiplier = (float) ($dish->percentage_profit ?? 0);
            $freshSuggestedPrice = round($currentDishCost * $profitMultiplier, 2);
            $designatedPrice = (float) ($dish->designated_price ?? 0);

            $updateFields = [];

            if (abs((float) $dish->cost_price - $currentDishCost) > 0.001) {
                $updateFields['cost_price'] = $currentDishCost;
                if ($hasCpvColumn) {
                    $updateFields['cpv'] = $currentDishCost;
                }
            }

            if (abs((float) $dish->suggested_price - $freshSuggestedPrice) > 0.001) {
                $updateFields['suggested_price'] = $freshSuggestedPrice;
            }

            // Si el precio de venta asignado es menor al precio sugerido por aumento de costos en inventario,
            // pasar el plato a 'En Revisión' (status = 2)
            if ($designatedPrice < $freshSuggestedPrice && (int) $dish->status !== 2) {
                $updateFields['status'] = 2; // En Revisión
                $flaggedUnderReviewCount++;
            }

            if (!empty($updateFields)) {
                $dish->update($updateFields);
                $updatedCount++;
            }
        }

        return [
            'updated_count' => $updatedCount,
            'flagged_under_review_count' => $flaggedUnderReviewCount,
        ];
    }

    /**
     * Obtener todos los platos con filtros y paginación.
     */
    public function getAll(array $filters): LengthAwarePaginator
    {
        // Al consultar la página 1 de platos o sub-recetas, sincronizar costos frescos de inventario
        if ((int) ($filters['page'] ?? 1) === 1) {
            try {
                $this->syncCostsAndFlagUnprofitableDishes();
            } catch (\Throwable $e) {
                \Log::warning('No se pudo sincronizar costos de platos al listar: ' . $e->getMessage());
            }
        }

        $query = Dish::with($this->getAvailableRelations());

        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('category', function($catQuery) use ($search) {
                      $catQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $statusVal = (int) $filters['status'];
            if ($statusVal === 3 && $this->schema()->hasColumn('dishes', 'output_product_id')) {
                $query->where(function ($q) {
                    $q->where('status', 3)
                      ->orWhereNotNull('output_product_id');
                });
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $itemsPerPage = (int) ($filters['itemsPerPage'] ?? ($filters['per_page'] ?? 15));
        if ($itemsPerPage === -1 || (isset($filters['all']) && filter_var($filters['all'], FILTER_VALIDATE_BOOLEAN))) {
            $itemsPerPage = 5000;
        } elseif ($itemsPerPage <= 0 || $itemsPerPage > 5000) {
            $itemsPerPage = 100;
        }
        
        // Ordenar con validación de columnas permitidas
        $allowedSortColumns = [
            'id',
            'name',
            'cost_price',
            'percentage_profit',
            'suggested_price',
            'designated_price',
            'status',
            'created_at',
        ];
        $sortBy = in_array($filters['sortBy'] ?? 'id', $allowedSortColumns, true)
            ? $filters['sortBy']
            : 'id';
        $orderBy = strtolower((string) ($filters['orderBy'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $orderBy);

        return $query->paginate($itemsPerPage);
    }

    /**
     * Encontrar un plato por ID y sincronizar costos actuales.
     */
    public function find(int $id): ?Dish
    {
        try {
            $this->syncCostsAndFlagUnprofitableDishes();
        } catch (\Throwable $e) {
            \Log::warning('No se pudo sincronizar costos generales antes de consultar plato: ' . $e->getMessage());
        }

        $dish = Dish::with($this->getAvailableRelations())->find($id);
        if ($dish) {
            $this->syncSingleDishCost($dish);
        }
        return $dish;
    }

    /**
     * Sincronizar costos frescos de un único plato.
     */
    public function syncSingleDishCost(Dish $dish): void
    {
        $schema = $this->schema();
        $currentCost = 0.0;
        $hasCpvColumn = $schema->hasColumn('dishes', 'cpv');
        $isSubRecipe = ((int) $dish->status === 3);

        foreach ($dish->ingredients as $ingredient) {
            $portion = (float) ($ingredient->pivot?->portion ?? 0);
            $unitCost = (float) ($ingredient->unit_cost ?? 0);
            $presentation = (float) ($ingredient->presentation ?? 1);
            $safePresentation = $presentation > 0 ? $presentation : 1.0;

            $freshDesignatedCost = round(($portion * $unitCost) / $safePresentation, 4);

            DB::table('dish_ingredients')
                ->where('dish_id', $dish->id)
                ->where('product_id', $ingredient->id)
                ->update(['designated_cost' => $freshDesignatedCost]);

            if ($ingredient->pivot) {
                $ingredient->pivot->designated_cost = $freshDesignatedCost;
            }

            $currentCost += $freshDesignatedCost;
        }

        $currentCost = round($currentCost * 1.05, 2);
        $profitMultiplier = (float) ($dish->percentage_profit ?? 0);
        $freshSuggestedPrice = round($currentCost * $profitMultiplier, 2);
        $designatedPrice = (float) ($dish->designated_price ?? 0);

        $updateFields = [];
        if (abs((float) $dish->cost_price - $currentCost) > 0.001) {
            $updateFields['cost_price'] = $currentCost;
            $dish->cost_price = $currentCost;
            if ($hasCpvColumn) {
                $updateFields['cpv'] = $currentCost;
                $dish->cpv = $currentCost;
            }
        }

        if (!$isSubRecipe) {
            if (abs((float) $dish->suggested_price - $freshSuggestedPrice) > 0.001) {
                $updateFields['suggested_price'] = $freshSuggestedPrice;
                $dish->suggested_price = $freshSuggestedPrice;
            }
            if ($designatedPrice < $freshSuggestedPrice && (int) $dish->status !== 2) {
                $updateFields['status'] = 2;
                $dish->status = 2;
            }
        } else {
            $updateFields['suggested_price'] = $currentCost;
            $dish->suggested_price = $currentCost;

            $yield = (float) ($dish->expected_yield_quantity ?? 1);
            $unitCostProduct = $yield > 0 ? round($currentCost / $yield, 4) : $currentCost;
            $ingredientIds = $dish->ingredients->pluck('id')->map(fn ($id) => (int) $id)->toArray();

            if (empty($dish->output_product_id) && $schema->hasColumn('dishes', 'output_product_id')) {
                $linkedProd = Product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $dish->name))])
                    ->where('is_sub_recipe', true)
                    ->whereNotIn('id', $ingredientIds)
                    ->first();
                if ($linkedProd) {
                    $dish->update(['output_product_id' => $linkedProd->id]);
                    $dish->output_product_id = $linkedProd->id;
                }
            }

            if (!empty($dish->output_product_id) && !in_array((int) $dish->output_product_id, $ingredientIds, true)) {
                Product::withoutEvents(function () use ($dish, $unitCostProduct) {
                    Product::where('id', $dish->output_product_id)->update([
                        'unit_cost' => $unitCostProduct,
                        'sale_price' => $unitCostProduct,
                    ]);
                });
            }
        }

        if (!empty($updateFields)) {
            $dish->update($updateFields);
        }
    }

    /**
     * Crear un nuevo plato y asociar ingredientes.
     */
    public function create(array $data): Dish
    {
        $schema = $this->schema();
        return DB::transaction(function () use ($data, $schema) {
            // Calcular costos si no vienen calculados del frontend (sumando 5% por imprevisto)
            $rawCost = 0;
            $ingredientsData = $data['ingredients'] ?? [];
            
            foreach ($ingredientsData as $ing) {
                $rawCost += (float) ($ing['designated_cost'] ?? 0);
            }

            $costPrice = round($rawCost * 1.05, 2);

            // Si no se envía cost_price, usar el calculado con imprevisto
            $data['cost_price'] = isset($data['cost_price']) ? (float) $data['cost_price'] : $costPrice;

            $cpvDeduction = 0;
            foreach ($ingredientsData as $ing) {
                if (!empty($ing['is_packaging']) || !empty($ing['cpv_deductible'])) {
                    $cpvDeduction += (float) ($ing['designated_cost'] ?? 0);
                }
            }
            $data['cpv'] = $data['cpv'] ?? ($data['cost_price'] - $cpvDeduction);

            $dishAttributes = [
                'name' => $data['name'],
                'cost_price' => $data['cost_price'],
                'suggested_price' => $data['suggested_price'],
                'designated_price' => $data['designated_price'],
                'percentage_profit' => $data['percentage_profit'],
                'category_id' => $data['category_id'] ?? null,
                'status' => $data['status'] ?? 1,
            ];

            if ($schema->hasColumn('dishes', 'cpv')) {
                $dishAttributes['cpv'] = $data['cpv'];
            }
            if ($schema->hasColumn('dishes', 'iva')) {
                $dishAttributes['iva'] = $data['iva'] ?? false;
            }
            if ($schema->hasColumn('dishes', 'tax_rate')) {
                $dishAttributes['tax_rate'] = $data['tax_rate'] ?? 16.00;
            }
            if ($schema->hasColumn('dishes', 'preparation_time_minutes')) {
                $dishAttributes['preparation_time_minutes'] = $data['preparation_time_minutes'] ?? 15;
            }
            if ($schema->hasColumn('dishes', 'kitchen_station_id')) {
                $dishAttributes['kitchen_station_id'] = $data['kitchen_station_id'] ?? null;
            }
            if ($schema->hasColumn('dishes', 'expected_yield_quantity') && isset($data['expected_yield_quantity'])) {
                $dishAttributes['expected_yield_quantity'] = max(0.01, (float) $data['expected_yield_quantity']);
            }
            if ($schema->hasColumn('dishes', 'shelf_life_days') && isset($data['shelf_life_days'])) {
                $dishAttributes['shelf_life_days'] = max(1, (int) $data['shelf_life_days']);
            }
            if ($schema->hasColumn('dishes', 'designated_price_cop') && array_key_exists('designated_price_cop', $data)) {
                $dishAttributes['designated_price_cop'] = $data['designated_price_cop'] !== null ? (float) $data['designated_price_cop'] : null;
            }

            // Crear plato
            $dish = Dish::create($dishAttributes);

            // Vincular ingredientes
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
                    $pivotData['is_removable'] = !empty($ing['is_removable']);
                }
                $dish->ingredients()->attach($ing['product_id'], $pivotData);
            }

            // Vincular grupos de modificadores si vienen en la petición y existe la tabla
            if (
                isset($data['modifier_group_ids'])
                && is_array($data['modifier_group_ids'])
                && $schema->hasTable('dish_modifier_group')
            ) {
                $dish->modifierGroups()->sync($data['modifier_group_ids']);
            }

            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            // Si es un plato base / sub-receta (status = 3) y no tiene output_product_id, crearlo y vincularlo
            if ((int) $dish->status === 3 && empty($dish->output_product_id)) {
                $yield = (float) ($dish->expected_yield_quantity ?: 1.0);
                $safeYield = $yield > 0 ? $yield : 1.0;
                $unitCostProd = round((float) $dish->cost_price / $safeYield, 4);
                $unitMeasure = !empty($data['unit_of_measure']) ? trim((string) $data['unit_of_measure']) : 'porciones';
                $ingredientIds = array_map(fn ($ing) => (int) ($ing['product_id'] ?? 0), $ingredientsData);

                $existingProd = Product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $dish->name))])
                    ->where('is_sub_recipe', true)
                    ->whereNotIn('id', $ingredientIds)
                    ->first();
                if ($existingProd) {
                    Product::withoutEvents(function () use ($existingProd, $unitCostProd, $unitMeasure) {
                        $existingProd->update([
                            'unit_cost' => $unitCostProd,
                            'sale_price' => $unitCostProd,
                            'unit_of_measure' => $unitMeasure,
                            'is_sub_recipe' => true,
                        ]);
                    });
                    $dish->update(['output_product_id' => $existingProd->id]);
                } else {
                    $productData = [
                        'name' => trim($dish->name),
                        'category_id' => $dish->category_id,
                        'sale_price' => $unitCostProd,
                        'unit_cost' => $unitCostProd,
                        'stock' => 0,
                        'presentation' => 1,
                        'unit_of_measure' => $unitMeasure,
                        'is_active' => true,
                        'is_deleted' => false,
                        'barcode' => 'SUB-' . date('ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        'active_ingredient' => 'Sub-receta / Insumo Cocina',
                        'iva' => false,
                        'is_sub_recipe' => true,
                        'yield_percentage' => 100.00,
                    ];

                    $outputProduct = Product::create($productData);
                    $dish->update(['output_product_id' => $outputProduct->id]);
                }
            }

            return $dish->load($this->getAvailableRelations());
        });
    }

    /**
     * Actualizar un plato existente y sincronizar ingredientes.
     */
    public function update(Dish $dish, array $data): Dish
    {
        $schema = $this->schema();
        return DB::transaction(function () use ($dish, $data, $schema) {
            $rawCost = 0;
            $ingredientsData = $data['ingredients'] ?? [];
            
            foreach ($ingredientsData as $ing) {
                $rawCost += (float) ($ing['designated_cost'] ?? 0);
            }

            $costPrice = round($rawCost * 1.05, 2);

            $data['cost_price'] = isset($data['cost_price']) ? (float) $data['cost_price'] : $costPrice;

            $cpvDeduction = 0;
            foreach ($ingredientsData as $ing) {
                if (!empty($ing['is_packaging']) || !empty($ing['cpv_deductible'])) {
                    $cpvDeduction += (float) ($ing['designated_cost'] ?? 0);
                }
            }
            $data['cpv'] = $data['cpv'] ?? ($data['cost_price'] - $cpvDeduction);

            $dishAttributes = [
                'name' => $data['name'],
                'cost_price' => $data['cost_price'],
                'suggested_price' => $data['suggested_price'],
                'designated_price' => $data['designated_price'],
                'percentage_profit' => $data['percentage_profit'],
                'category_id' => $data['category_id'] ?? null,
                'status' => $data['status'] ?? 1,
            ];

            if ($schema->hasColumn('dishes', 'cpv')) {
                $dishAttributes['cpv'] = $data['cpv'];
            }
            if ($schema->hasColumn('dishes', 'iva')) {
                $dishAttributes['iva'] = $data['iva'] ?? $dish->iva;
            }
            if ($schema->hasColumn('dishes', 'tax_rate')) {
                $dishAttributes['tax_rate'] = $data['tax_rate'] ?? $dish->tax_rate;
            }
            if ($schema->hasColumn('dishes', 'preparation_time_minutes')) {
                $dishAttributes['preparation_time_minutes'] = $data['preparation_time_minutes'] ?? $dish->preparation_time_minutes;
            }
            if ($schema->hasColumn('dishes', 'kitchen_station_id')) {
                $dishAttributes['kitchen_station_id'] = $data['kitchen_station_id'] ?? $dish->kitchen_station_id;
            }
            if ($schema->hasColumn('dishes', 'expected_yield_quantity') && isset($data['expected_yield_quantity'])) {
                $dishAttributes['expected_yield_quantity'] = max(0.01, (float) $data['expected_yield_quantity']);
            }
            if ($schema->hasColumn('dishes', 'shelf_life_days') && isset($data['shelf_life_days'])) {
                $dishAttributes['shelf_life_days'] = max(1, (int) $data['shelf_life_days']);
            }
            if ($schema->hasColumn('dishes', 'designated_price_cop') && array_key_exists('designated_price_cop', $data)) {
                $dishAttributes['designated_price_cop'] = $data['designated_price_cop'] !== null ? (float) $data['designated_price_cop'] : null;
            }

            // Actualizar plato
            $dish->update($dishAttributes);

            // Sincronizar ingredientes
            $conn = $dish->getConnection();
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
                    $pivotData['is_removable'] = !empty($ing['is_removable']);
                }
                $syncData[$ing['product_id']] = $pivotData;
            }
            $dish->ingredients()->sync($syncData);

            // Sincronizar grupos de modificadores si vienen en la petición y existe la tabla
            if (
                isset($data['modifier_group_ids'])
                && is_array($data['modifier_group_ids'])
                && $schema->hasTable('dish_modifier_group')
            ) {
                $dish->modifierGroups()->sync($data['modifier_group_ids']);
            }

            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            // Si es un plato base / sub-receta (status = 3) y no tiene output_product_id, crearlo y vincularlo
            if ((int) $dish->status === 3) {
                $yield = (float) ($dish->expected_yield_quantity ?: 1.0);
                $safeYield = $yield > 0 ? $yield : 1.0;
                $unitCostProd = round((float) $dish->cost_price / $safeYield, 4);
                $ingredientIds = array_map(fn ($ing) => (int) ($ing['product_id'] ?? 0), $ingredientsData);

                // Si output_product_id apunta por error a uno de sus propios ingredientes, desvincularlo para crear el elaborado
                if (!empty($dish->output_product_id) && in_array((int) $dish->output_product_id, $ingredientIds, true)) {
                    $dish->output_product_id = null;
                }

                if (empty($dish->output_product_id)) {
                    $existingProd = Product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $dish->name))])
                        ->where('is_sub_recipe', true)
                        ->whereNotIn('id', $ingredientIds)
                        ->first();
                    if ($existingProd) {
                        $dish->update(['output_product_id' => $existingProd->id]);
                        $dish->output_product_id = $existingProd->id;
                    }
                }

                if (empty($dish->output_product_id)) {
                    $unitMeasure = !empty($data['unit_of_measure']) ? trim((string) $data['unit_of_measure']) : 'porciones';
                    $productData = [
                        'name' => trim($dish->name),
                        'category_id' => $dish->category_id,
                        'sale_price' => $unitCostProd,
                        'unit_cost' => $unitCostProd,
                        'stock' => 0,
                        'presentation' => 1,
                        'unit_of_measure' => $unitMeasure,
                        'is_active' => true,
                        'is_deleted' => false,
                        'barcode' => 'SUB-' . date('ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        'active_ingredient' => 'Sub-receta / Insumo Cocina',
                        'iva' => false,
                        'is_sub_recipe' => true,
                        'yield_percentage' => 100.00,
                    ];
                    $outputProduct = Product::create($productData);
                    $dish->update(['output_product_id' => $outputProduct->id]);
                } else {
                    $prodUpdate = [
                        'name' => trim($dish->name),
                        'unit_cost' => $unitCostProd,
                        'sale_price' => $unitCostProd,
                        'is_sub_recipe' => true,
                    ];
                    if (!empty($data['unit_of_measure'])) {
                        $prodUpdate['unit_of_measure'] = trim((string) $data['unit_of_measure']);
                    }
                    Product::withoutEvents(function () use ($dish, $prodUpdate) {
                        Product::where('id', $dish->output_product_id)->update($prodUpdate);
                    });
                }

                // Propagar el nuevo costo unitario de esta sub-receta a los platos que la utilicen
                try {
                    $this->syncCostsAndFlagUnprofitableDishes();
                } catch (\Throwable $e) {
                    \Log::warning('Error propagando costo de sub-receta tras actualizar: ' . $e->getMessage());
                }
            }

            return $dish->load($this->getAvailableRelations());
        });
    }

    /**
     * Eliminar un plato.
     */
    public function delete(Dish $dish): bool
    {
        return DB::transaction(function () use ($dish) {
            $dish->ingredients()->detach();
            if ($this->schema()->hasTable('dish_modifier_group')) {
                $dish->modifierGroups()->detach();
            }
            
            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            return (bool) $dish->delete();
        });
    }

    /**
     * Eliminar ingredientes de costo fijo e imprevisto de todos los platos
     * y recalcular todos los costos sin modificar el precio de venta (designated_price).
     */
    public function removeFixedCostsAndRecalculate(): array
    {
        return DB::transaction(function () {
            // Identificar productos cuyos nombres correspondan a costo fijo o imprevisto
            $targetProductIds = DB::table('products')
                ->where(function ($query) {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%costo fijo%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%costos fijos%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%costo_fijo%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%gasto fijo%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%gastos fijos%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%imprevisto%']);
                })
                ->pluck('id')
                ->toArray();

            $removedRelationsCount = 0;
            if (!empty($targetProductIds)) {
                $removedRelationsCount = DB::table('dish_ingredients')
                    ->whereIn('product_id', $targetProductIds)
                    ->delete();
            }

            $hasCpvColumn = $this->schema()->hasColumn('dishes', 'cpv');
            $dishSelectColumns = [
                'id',
                'cost_price',
                'suggested_price',
                'designated_price',
                'percentage_profit',
            ];
            if ($hasCpvColumn) {
                $dishSelectColumns[] = 'cpv';
            }

            // Cargar todos los platos con sus ingredientes restantes para recalcular costos
            $dishes = Dish::select($dishSelectColumns)->with([
                'ingredients' => function ($query) {
                    $query->select([
                        'products.id',
                        'products.name',
                        'products.unit_cost',
                        'products.presentation',
                    ]);
                },
            ])->get();

            $recalculatedDishesCount = 0;

            foreach ($dishes as $dish) {
                $newCostPrice = 0.0;

                foreach ($dish->ingredients as $ingredient) {
                    $portion = (float) ($ingredient->pivot?->portion ?? 0);
                    $designatedCost = (float) ($ingredient->pivot?->designated_cost ?? 0);
                    $unitCost = (float) ($ingredient->unit_cost ?? 0);
                    $presentation = (float) ($ingredient->presentation ?? 1);

                    // Si el costo asignado de la porción es 0 y el insumo posee costo unitario, calcularlo
                    if ($designatedCost <= 0 && $unitCost > 0 && $portion > 0) {
                        $safePresentation = $presentation > 0 ? $presentation : 1.0;
                        $designatedCost = round(($portion * $unitCost) / $safePresentation, 2);

                        if ($ingredient->pivot?->id) {
                            DB::table('dish_ingredients')
                                ->where('id', $ingredient->pivot->id)
                                ->update(['designated_cost' => $designatedCost]);
                        }
                    }

                    $newCostPrice += $designatedCost;
                }

                $newCostPrice = round($newCostPrice, 2);
                $profitMultiplier = (float) ($dish->percentage_profit ?? 0);
                $newSuggestedPrice = round($newCostPrice * $profitMultiplier, 2);

                // Actualizar únicamente costos y sugerido, manteniendo intacto designated_price (precio de venta)
                $updateFields = [
                    'cost_price' => $newCostPrice,
                    'suggested_price' => $newSuggestedPrice,
                ];
                if ($hasCpvColumn) {
                    $updateFields['cpv'] = $newCostPrice;
                }

                $dish->update($updateFields);

                $recalculatedDishesCount++;
            }

            \Cache::forget('resources.categories.dishes');

            return [
                'removed_ingredients_count' => $removedRelationsCount,
                'recalculated_dishes_count' => $recalculatedDishesCount,
                'message' => "Se eliminaron {$removedRelationsCount} registros de costo fijo/imprevisto y se recalcularon los costos de {$recalculatedDishesCount} platos sin modificar su precio de venta.",
            ];
        });
    }
}
