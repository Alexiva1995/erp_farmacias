<?php


namespace App\Data;

use Spatie\LaravelData\Data;

class CreateTransactionData extends Data
{

    public function __construct(
        public int        $user_id,
        public int        $category_id,
        public string     $description,
        public string     $currency,
        public string     $type,
        public float      $amount,
        public string     $movement_type,
        public string     $transaction_date
    ) {}
}
