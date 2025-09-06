<?php


namespace App\Contracts;

use App\Exports\ExpenseExport;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface Expenses
{


    public function crearGasto(array $data): Expense;

    public function editarGasto(array $data): Expense;

    public function consultById(string $id): ?Expense;

    public function consultAll(): Collection;

    public function deleteById(string $id): void;

    public function filterWithPaginate(array $filtros, int $perPage = 10): LengthAwarePaginator;

    public function filterWithoutPaginate(array $filtros): Collection;

    public function changeStatus(int $id, string $status): Expense;

    public function exportExcel(array $filtros): ExpenseExport;
}
