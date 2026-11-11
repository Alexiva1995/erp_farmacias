<?php

namespace App\Contracts\Repositories;

use App\Models\Product;

interface ZoomProductRepositoryInterface
{
    /**
     * Buscar el producto único de Zoom o crearlo si no existe en el catálogo.
     */
    public function findOrCreateZoomProduct(bool $isTaxable = true): Product;

    /**
     * Garantizar que el producto Zoom cuente con stock disponible en su lote.
     */
    public function ensureStock(Product $product, ?string $lotNumber = null): void;
}
