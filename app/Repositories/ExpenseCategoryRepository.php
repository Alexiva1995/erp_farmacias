<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryRepository implements \App\Contracts\ExpenseCategory
{


    public function getAll(): Collection
    {
        return ExpenseCategory::query()
            ->orderBy("name", "ASC")->get();
    }
}
