<?php


namespace App\Data;

use Spatie\LaravelData\Data;

class ChangeStatusExpenseData extends Data
{
    public function __construct(
        public int          $id,
        public string       $status,
    ) {}
}
