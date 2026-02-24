<?php

namespace App\Data;

use Spatie\LaravelData\Data;


class ExchangeRateCreateData extends Data
{

    public function __construct(
        public int|null $id,
        public string $currency_code,
        public float|null $rate,
        public string|null $source
    ) {
    }
}
