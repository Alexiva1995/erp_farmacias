<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ExpenseCategory as ExpenseCategoryContract;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryRepository implements ExpenseCategoryContract
{
    public function getAll(): Collection
    {
        return ExpenseCategory::query()
            ->select(['id', 'name', 'created_at', 'updated_at'])
            ->withCount(['expenses', 'recurringExpenses', 'quickExpenses'])
            ->orderBy("name", "ASC")
            ->get();
    }

    public function findById(int $id): ?ExpenseCategory
    {
        return ExpenseCategory::query()
            ->select(['id', 'name', 'created_at', 'updated_at'])
            ->withCount(['expenses', 'recurringExpenses', 'quickExpenses'])
            ->find($id);
    }

    public function create(array $data): ExpenseCategory
    {
        return ExpenseCategory::create($data);
    }

    public function update(int $id, array $data): ExpenseCategory
    {
        $category = ExpenseCategory::findOrFail($id);
        $category->update($data);

        return $category;
    }

    public function delete(int $id): bool
    {
        $category = ExpenseCategory::findOrFail($id);

        return (bool) $category->delete();
    }
}
