<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ExpenseCategory;
use App\Repositories\ExpenseCategoryRepository;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryServices implements ExpenseCategory
{

    public function __construct(
        protected ExpenseCategoryRepository $expenseCategoryRepository
    ) {}

    public function getAll(): Collection
    {
        return $this->expenseCategoryRepository->getAll();
    }
}
