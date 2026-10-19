<?php

declare(strict_types=1);

namespace App\Services\Order\Strategies;

use App\Contracts\CurrencyPricingStrategy;
use App\Models\Product;
use App\Models\OrderDetail;

class CopPricingStrategy implements CurrencyPricingStrategy
{
    public function calculatePrice(Product $product, ?OrderDetail $detail = null): float
    {
        if ($detail && $detail->unit_price_usd > 0) {
            $isZoom = $product->barcode === 'ZOOM-SERVICE' || $product->name === 'SERVICIO ZOOM' || (float) ($product->sale_price ?? 0) <= 0;

            if ($isZoom) {
                $tasaCop = (float) (app(\App\Services\Resources\ResourceService::class)->getExchangeRate('COP') ?: 1);
                return (float) (ceil(round((float) $detail->unit_price_usd * $tasaCop) / 100) * 100);
            }

            if ($detail->pack_id) {
                $salePrice = $product->sale_price ?? 0;
                $priceCop = $product->price_cop ?? 0;
                $rate = ($salePrice > 0) ? ($priceCop / $salePrice) : 0;
                return (float) ($detail->unit_price_usd * $rate);
            }
        }
        return (float) ($product->price_cop ?? 0.0);
    }
}
