<?php

namespace App\Repositories;

use App\Contracts\ProductVariantRepositoryInterface;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class EloquentProductVariantRepository implements ProductVariantRepositoryInterface
{
    /**
     * Obtener todas las variantes de un producto específico.
     */
    public function getByProductId(int $productId): Collection
    {
        return ProductVariant::where('product_id', $productId)->get();
    }

    /**
     * Buscar una variante por su ID.
     */
    public function find(int $id): ?ProductVariant
    {
        return ProductVariant::find($id);
    }

    /**
     * Crear una nueva variante.
     */
    public function create(array $data): ProductVariant
    {
        return ProductVariant::create($data);
    }

    /**
     * Actualizar una variante.
     */
    public function update(int $id, array $data): bool
    {
        $variant = $this->find($id);
        if ($variant) {
            return $variant->update($data);
        }
        return false;
    }

    /**
     * Eliminar una variante.
     */
    public function delete(int $id): bool
    {
        $variant = $this->find($id);
        if ($variant) {
            return $variant->delete();
        }
        return false;
    }
}
