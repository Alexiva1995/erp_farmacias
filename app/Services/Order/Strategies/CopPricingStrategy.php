<?php

namespace App\Services\Order\Strategies;

use App\Contracts\CurrencyPricingStrategy;
use App\Models\Product;
use App\Models\OrderDetail;

class CopPricingStrategy implements CurrencyPricingStrategy
{
    public function calculatePrice(Product $product, ?OrderDetail $detail = null): float
    {
        if ($detail && $detail->pack_id && $detail->unit_price_usd > 0) {
            $salePrice = $product->sale_price ?? 0;
            $priceCop = $product->price_cop ?? 0;
            $rate = ($salePrice > 0) ? ($priceCop / $salePrice) : 0;
            return (float) ($detail->unit_price_usd * $rate);
        }
        return (float) ($product->price_cop ?? 0.0);
    }
}
