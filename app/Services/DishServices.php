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
        $query = Dish::with(['category', 'ingredients']);

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

        $itemsPerPage = $filters['itemsPerPage'] ?? 1000;
        if ($itemsPerPage == -1) {
            $itemsPerPage = 10000;
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
        return Dish::with(['category', 'ingredients'])->find($id);
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

            // Calcular CPV (Costo de venta). En Toffle se deducían empaques. 
            // Si el frontend marca qué es empaque o deducible, podemos procesarlo.
            // Para ser retrocompatible, calculamos cost_price menos ingredientes especiales.
            $cpvDeduction = 0;
            foreach ($ingredientsData as $ing) {
                // Si el producto/ingrediente viene marcado como embalaje/empaque o deducible del CPV
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
            ]);

            // Vincular ingredientes
            foreach ($ingredientsData as $ing) {
                $dish->ingredients()->attach($ing['product_id'], [
                    'portion' => $ing['portion'],
                    'designated_cost' => $ing['designated_cost'],
                ]);
            }

            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            return $dish->load(['category', 'ingredients']);
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
            ]);

            // Sincronizar ingredientes
            $syncData = [];
            foreach ($ingredientsData as $ing) {
                $syncData[$ing['product_id']] = [
                    'portion' => $ing['portion'],
                    'designated_cost' => $ing['designated_cost'],
                ];
            }
            $dish->ingredients()->sync($syncData);

            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            return $dish->load(['category', 'ingredients']);
        });
    }

    /**
     * Eliminar un plato.
     */
    public function delete(Dish $dish): bool
    {
        return DB::transaction(function () use ($dish) {
            // Verificar si el plato está asociado a órdenes en el nuevo sistema
            // (La tabla orders o order_details podría relacionarse en el futuro).
            // Por ahora, eliminamos la relación de ingredientes y luego el plato.
            $dish->ingredients()->detach();
            
            // Limpiar caché de categorías de platos
            \Cache::forget('resources.categories.dishes');

            return (bool) $dish->delete();
        });
    }
}
