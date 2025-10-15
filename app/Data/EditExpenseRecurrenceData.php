<?php

namespace App\Data;


class EditExpenseRecurrenceData extends CreateExpenseData
{

    public function __construct(
        public int          $id,
        public string       $name,
        public int          $category_id,
        public float        $amount,
        public float        $amount_usd,
        public string       $currency,
        public bool|null    $has_invoice,
        public bool|null    $is_deductible,
        public bool         $iva = false,
        // public DateTime $expense_date,
        public int          $user_id,
        public string       $count,
        public string       $type_of_expense,
        public string       $recurrence,
        public ?string      $next_expense_date = null,
        public ?string      $status,
    ) {}
}
