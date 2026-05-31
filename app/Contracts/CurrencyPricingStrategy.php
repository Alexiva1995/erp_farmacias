<?php

namespace App\Contracts;

use App\Models\Product;
use App\Models\OrderDetail;

interface CurrencyPricingStrategy
{
    /**
     * Calcula el precio del producto o detalle adaptado a la divisa seleccionada.
     *
     * @param Product $product
     * @param OrderDetail|null $detail
     * @return float
     */
    public function calculatePrice(Product $product, ?OrderDetail $detail = null): float;
}
