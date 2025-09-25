<?php


namespace App\Repository;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryRepository
{


    public function getAll(): Collection
    {
        return ExpenseCategory::query()
            ->orderBy("name", "ASC")->get();
    }
}
