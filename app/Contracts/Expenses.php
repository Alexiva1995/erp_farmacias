<?php


namespace App\Contracts;

use App\Data\CreateExpenseData;
use App\Data\CreateExpenseRecurrenceData;
use App\Exports\ExpenseExport;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface Expenses
{
    public function create(CreateExpenseData $data): Expense;

    public function createRecurring(CreateExpenseRecurrenceData $data): Expense;

    public function update(array $data): Expense;

    public function uploadInvoice(array $data): Expense;

    public function findById(string $id): ?Expense;

    public function getAll(): Collection;

    public function deleteById(string $id): void;

    public function filterWithPaginate(array $filters, int $perPage = 10): LengthAwarePaginator;

    public function filterWithoutPaginate(array $filters): Collection;

    public function updateStatus(int $id, string $status): Expense;

    public function exportToExcel(array $filters): ExpenseExport;

    public function executeRecurringExpensesOfToday(): void;

    public function getGlobalStats(array $filters): array;
}
