<?php

namespace App\Services;

use App\Contracts\Expenses;
use App\Repository\ExpensesRepository;

class ExpensesServices implements Expenses
{


    public function __construct(
        protected ExpensesRepository $expensesRepository
    ) {}
}
