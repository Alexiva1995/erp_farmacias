<?php

namespace App\Data;

use Spatie\LaravelData\Data;


class ProfitabilityEditData extends Data
{

    public function __construct(
        public int $id,
        public int $product_id,
        public float $profitability_percentage,
        public int $is_locked
    ) {}
}
