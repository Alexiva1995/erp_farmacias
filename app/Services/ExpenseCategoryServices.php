<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ExpenseCategory;
use App\Models\ExpenseCategory as ExpenseCategoryModel;
use App\Repositories\ExpenseCategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryServices implements ExpenseCategory
{
    public function __construct(
        protected ExpenseCategoryRepository $expenseCategoryRepository
    ) {}

    public function getAll(): Collection
    {
        return $this->expenseCategoryRepository->getAll();
    }

    public function findById(int $id): ?ExpenseCategoryModel
    {
        return $this->expenseCategoryRepository->findById($id);
    }

    public function create(array $data): ExpenseCategoryModel
    {
        return $this->expenseCategoryRepository->create($data);
    }

    public function update(int $id, array $data): ExpenseCategoryModel
    {
        return $this->expenseCategoryRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        $category = $this->expenseCategoryRepository->findById($id);

        if (!$category) {
            throw ValidationException::withMessages([
                'category' => ['La categoría especificada no existe.'],
            ]);
        }

        $hasExpenses = ($category->expenses_count ?? 0) > 0;
        $hasRecurring = ($category->recurring_expenses_count ?? 0) > 0;
        $hasQuick = ($category->quick_expenses_count ?? 0) > 0;

        if ($hasExpenses || $hasRecurring || $hasQuick) {
            throw ValidationException::withMessages([
                'category' => ['No se puede eliminar esta categoría porque ya tiene gastos o plantillas asociadas.'],
            ]);
        }

        return $this->expenseCategoryRepository->delete($id);
    }
}
