<?php

namespace App\Services\Order\Strategies;

use App\Contracts\CurrencyPricingStrategy;
use InvalidArgumentException;

class PricingStrategyFactory
{
    /**
     * Construye y retorna la estrategia de precios correcta de acuerdo al código de divisa.
     *
     * @param string $currency
     * @return CurrencyPricingStrategy
     */
    public static function make(string $currency): CurrencyPricingStrategy
    {
        return match (strtoupper($currency)) {
            'USD' => new UsdPricingStrategy(),
            'BS', 'VES' => new BsPricingStrategy(),
            'COP' => new CopPricingStrategy(),
            default => throw new InvalidArgumentException("Moneda no soportada por el sistema: {$currency}"),
        };
    }
}
