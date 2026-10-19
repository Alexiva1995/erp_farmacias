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
        if ($detail && ($detail->unit_price_usd > 0 || $detail->price_bs > 0)) {
            $isZoom = $product->barcode === 'ZOOM-SERVICE' || $product->name === 'SERVICIO ZOOM' || (float) ($product->sale_price ?? 0) <= 0;

            if ($isZoom) {
                if ((float) ($detail->price_bs ?? 0) > 0) {
                    return (float) $detail->price_bs;
                }
                $tasaBs = (float) (app(\App\Services\Resources\ResourceService::class)->getExchangeRate('BS') ?: 1);
                return round((float) $detail->unit_price_usd * $tasaBs, 2);
            }

            if ($detail->pack_id && $detail->unit_price_usd > 0) {
                $salePrice = $product->sale_price ?? 0;
                $priceBs = $product->price_bs ?? 0;
                $rate = ($salePrice > 0) ? ($priceBs / $salePrice) : 0;
                if ($rate == 0 && $priceBs > 0) {
                    $rate = 1;
                }
                return (float) ($detail->unit_price_usd * $rate);
            }
        }
        return (float) ($product->price_bs ?? 0.0);
    }
}
