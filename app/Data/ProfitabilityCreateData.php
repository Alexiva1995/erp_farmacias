<?php

namespace App\Data;

use Spatie\LaravelData\Data;


class ProfitabilityCreateData extends Data
{

    public function __construct(
        public int $product_id,
        public float $profitability_percentage,
        public int $is_locked,
        public ?float $shipping_cost = null,
        public ?float $packaging_cost = null,
        public ?float $expense_margin = null,
        public ?float $profit_margin = null,
        public ?float $tax_usa = null,
    ) {}
}
