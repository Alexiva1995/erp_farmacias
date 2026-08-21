<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\ExpenseCategory as ExpenseCategoryModel;
use Illuminate\Database\Eloquent\Collection;

interface ExpenseCategory
{
    public function getAll(): Collection;

    public function findById(int $id): ?ExpenseCategoryModel;

    public function create(array $data): ExpenseCategoryModel;

    public function update(int $id, array $data): ExpenseCategoryModel;

    public function delete(int $id): bool;
}

