<?php

namespace App\Contracts\Zoom;

use App\Models\Product;

interface ZoomServiceInterface
{
    /**
     * Obtener el producto base de Zoom configurado.
     */
    public function getZoomProduct(): Product;

    /**
     * Agregar un ítem de Zoom a la orden activa del vendedor con su descripción y monto sin IVA.
     */
    public function addZoomItemToOrder(array $data, int $sellerId): array;
}
