<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Dish as DishContract;
use App\Models\Dish;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DishServices implements DishContract
{
    /**
     * Obtener todos los platos con filtros y paginación.
     */
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Dish::with(['category', 'ingredients', 'modifierGroups.options.product']);

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
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $itemsPerPage = (int) ($filters['itemsPerPage'] ?? 15);
        if ($itemsPerPage <= 0 || $itemsPerPage > 500) {
            $itemsPerPage = 100;
        }
        
        // Ordenar
        $sortBy = $filters['sortBy'] ?? 'id';
        $orderBy = $filters['orderBy'] ?? 'desc';
        $query->orderBy($sortBy, $orderBy);

        return $query->paginate($itemsPerPage);
    }

    /**
     * Encontrar un plato por ID.
     */
    public function find(int $id): ?Dish
    {
        return Dish::with(['category', 'ingredients', 'modifierGroups.options.product'])->find($id);
    }

    /**
     * Crear un nuevo plato y asociar ingredientes.
     */
    public function create(array $data): Dish
    {
        return DB::transaction(function () use ($data) {
            // Calcular costos si no vienen calculados del frontend
            $costPrice = 0;
            $ingredientsData = $data['ingredients'] ?? [];
            
            foreach ($ingredientsData as $ing) {
                $costPrice += (float) ($ing['designated_cost'] ?? 0);
            }

            // Si no se envía cost_price, usar el calculado
            $data['cost_price'] = $data['cost_price'] ?? $costPrice;

            $cpvDeduction = 0;
            foreach ($ingredientsData as $ing) {
                if (!empty($ing['is_packaging']) || !empty($ing['cpv_deductible'])) {
                    $cpvDeduction += (float) ($ing['designated_cost'] ?? 0);
                }
            }
            $data['cpv'] = $data['cpv'] ?? ($data['cost_price'] - $cpvDeduction);

            // Crear plato
            $dish = Dish::create([
                'name' => $data['name'],
                'cost_price' => $data['cost_price'],
                'cpv' => $data['cpv'],
                'suggested_price' => $data['suggested_price'],
                'designated_price' => $data['designated_price'],
                'percentage_profit' => $data['percentage_profit'],
                'category_id' => $data['category_id'] ?? null,
                'status' => $data['status'] ?? 1,
                'iva' => $data['iva'] ?? false,
                'tax_rate' => $data['tax_rate'] ?? 16.00,
                'preparation_time_minutes' => $data['preparation_time_minutes'] ?? 15,
                'kitchen_station_id' => $data['kitchen_station_id'] ?? null,
            ]);

            // Vincular ingredientes
            foreach ($ingredientsData as $ing) {
                $dish->ingredients()->attach($ing['product_id'], [
                    'portion' => $ing['portion'],
                    'designated_cost' => $ing['designated_cost'],
                    'is_removable' => !empty($ing['is_removable']),
                ]);
            }

            // Vincular grupos de modificadores si vienen en la petición
            if (isset($data['modifier_group_ids']) && is_array($data['modifier_group_ids'])) {
                $dish->modifierGroups()->sync($data['modifier_group_ids']);
            }

            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            return $dish->load(['category', 'ingredients', 'modifierGroups.options.product']);
        });
    }

    /**
     * Actualizar un plato existente y sincronizar ingredientes.
     */
    public function update(Dish $dish, array $data): Dish
    {
        return DB::transaction(function () use ($dish, $data) {
            $costPrice = 0;
            $ingredientsData = $data['ingredients'] ?? [];
            
            foreach ($ingredientsData as $ing) {
                $costPrice += (float) ($ing['designated_cost'] ?? 0);
            }

            $data['cost_price'] = $data['cost_price'] ?? $costPrice;

            $cpvDeduction = 0;
            foreach ($ingredientsData as $ing) {
                if (!empty($ing['is_packaging']) || !empty($ing['cpv_deductible'])) {
                    $cpvDeduction += (float) ($ing['designated_cost'] ?? 0);
                }
            }
            $data['cpv'] = $data['cpv'] ?? ($data['cost_price'] - $cpvDeduction);

            // Actualizar plato
            $dish->update([
                'name' => $data['name'],
                'cost_price' => $data['cost_price'],
                'cpv' => $data['cpv'],
                'suggested_price' => $data['suggested_price'],
                'designated_price' => $data['designated_price'],
                'percentage_profit' => $data['percentage_profit'],
                'category_id' => $data['category_id'] ?? null,
                'status' => $data['status'] ?? 1,
                'iva' => $data['iva'] ?? $dish->iva,
                'tax_rate' => $data['tax_rate'] ?? $dish->tax_rate,
                'preparation_time_minutes' => $data['preparation_time_minutes'] ?? $dish->preparation_time_minutes,
                'kitchen_station_id' => $data['kitchen_station_id'] ?? $dish->kitchen_station_id,
            ]);

            // Sincronizar ingredientes
            $syncData = [];
            foreach ($ingredientsData as $ing) {
                $syncData[$ing['product_id']] = [
                    'portion' => $ing['portion'],
                    'designated_cost' => $ing['designated_cost'],
                    'is_removable' => !empty($ing['is_removable']),
                ];
            }
            $dish->ingredients()->sync($syncData);

            // Sincronizar grupos de modificadores si vienen en la petición
            if (isset($data['modifier_group_ids']) && is_array($data['modifier_group_ids'])) {
                $dish->modifierGroups()->sync($data['modifier_group_ids']);
            }

            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            return $dish->load(['category', 'ingredients', 'modifierGroups.options.product']);
        });
    }

    /**
     * Eliminar un plato.
     */
    public function delete(Dish $dish): bool
    {
        return DB::transaction(function () use ($dish) {
            $dish->ingredients()->detach();
            $dish->modifierGroups()->detach();
            
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

            // Cargar todos los platos con sus ingredientes restantes para recalcular costos
            $dishes = Dish::select([
                'id',
                'cost_price',
                'cpv',
                'suggested_price',
                'designated_price',
                'percentage_profit',
            ])->with([
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
                $dish->update([
                    'cost_price' => $newCostPrice,
                    'cpv' => $newCostPrice,
                    'suggested_price' => $newSuggestedPrice,
                ]);

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
