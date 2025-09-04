<?php

namespace App\Services;

use App\Contracts\Expenses;
use App\Models\Expense;
use App\Repository\ExpensesRepository;

class ExpensesServices implements Expenses
{


    public function __construct(
        protected ExpensesRepository $expensesRepository
    ) {}


    public function crearGasto(array $data): Expense
    {
        return $this->expensesRepository->createGasto($data);
    }
}
