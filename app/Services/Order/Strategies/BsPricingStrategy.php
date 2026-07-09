<?php

declare(strict_types=1);

namespace App\Services\Order\Strategies;

use App\Contracts\CurrencyPricingStrategy;
use App\Models\Product;
use App\Models\OrderDetail;

class BsPricingStrategy implements CurrencyPricingStrategy
{
    public function calculatePrice(Product $product, ?OrderDetail $detail = null): float
    {
        if ($detail && $detail->pack_id && $detail->unit_price_usd > 0) {
            $salePrice = $product->sale_price ?? 0;
            $priceBs = $product->price_bs ?? 0;
            $rate = ($salePrice > 0) ? ($priceBs / $salePrice) : 0;
            if ($rate == 0 && $priceBs > 0) {
                $rate = 1;
            }
            return (float) ($detail->unit_price_usd * $rate);
        }
        return (float) ($product->price_bs ?? 0.0);
    }
}
