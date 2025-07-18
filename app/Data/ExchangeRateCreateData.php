<?php

namespace App\Data;

use Spatie\LaravelData\Data;


class ExchangeRateCreateData extends Data
{

    public function __construct(
        public string $currency_code,
        public float $rate,
        public string|null $source
    ) {}
}
