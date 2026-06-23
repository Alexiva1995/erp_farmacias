<?php

namespace App\Contracts;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;

interface ProductVariantRepositoryInterface
{
    /**
     * Obtener todas las variantes de un producto específico.
     */
    public function getByProductId(int $productId): Collection;

    /**
     * Buscar una variante por su ID.
     */
    public function find(int $id): ?ProductVariant;

    /**
     * Crear una nueva variante.
     */
    public function create(array $data): ProductVariant;

    /**
     * Actualizar una variante.
     */
    public function update(int $id, array $data): bool;

    /**
     * Eliminar una variante.
     */
    public function delete(int $id): bool;
}
