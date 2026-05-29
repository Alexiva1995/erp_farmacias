<?php

namespace App\Services\Order\Strategies;

use App\Contracts\CurrencyPricingStrategy;
use App\Models\Product;
use App\Models\OrderDetail;

class UsdPricingStrategy implements CurrencyPricingStrategy
{
    public function calculatePrice(Product $product, ?OrderDetail $detail = null): float
    {
        if ($detail && $detail->pack_id && $detail->unit_price_usd > 0) {
            return (float) $detail->unit_price_usd;
        }
        return (float) ($product->sale_price ?? $product->price ?? 0.0);
    }
}
