<?php


namespace App\Contracts;

use App\Models\Expense;

interface Expenses
{


    public function crearGasto(array $data): Expense;
}
