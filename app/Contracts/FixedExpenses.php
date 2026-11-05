<?php

namespace App\Contracts;

use App\Data\CreateExpenseData;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use Illuminate\Pagination\LengthAwarePaginator;

interface FixedExpenses
{
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator;

    public function create(array $data): FixedExpense;

    public function update(int $id, array $data): FixedExpense;

    public function delete(int $id): void;

    public function toggleActive(int $id): FixedExpense;

    public function generateDueOccurrences(): int;

    public function paginateOccurrences(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function getOccurrencesSummary(): array;

    public function payOccurrence(int $occurrenceId, CreateExpenseData $data): FixedExpenseOccurrence;

    public function skipOccurrence(int $occurrenceId, ?string $note): FixedExpenseOccurrence;
}
