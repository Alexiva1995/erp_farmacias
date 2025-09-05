<?php

namespace App\Services;

use App\Contracts\Expenses;
use App\Models\Expense;
use App\Repository\ExpensesRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpensesServices implements Expenses
{


    public function __construct(
        protected ExpensesRepository $expensesRepository
    ) {}


    public function crearGasto(array $data): Expense
    {
        // $data
        return $this->expensesRepository->createGasto($data);
    }

    public function editarGasto(array $data): Expense
    {
        return $this->expensesRepository->edit($data);
    }

    public function consultById(string $id): ?Expense
    {
        return $this->expensesRepository->consultById($id);
    }

    public function consultAll(): Collection
    {
        return $this->expensesRepository->consultAll();
    }

    public function deleteById(string $id): void
    {
        $this->expensesRepository->deleteById($id);
    }

    public function filterWithPaginate(array $filtros, int $perPage = 10): LengthAwarePaginator
    {
        return $this->expensesRepository->filterWithPaginate($filtros, $perPage);
    }

    public function filterWithoutPaginate(array $filtros): Collection
    {
        return $this->expensesRepository->filterWithoutPaginate($filtros);
    }

    public function changeStatus(int $id, string $status): Expense
    {
        return $this->expensesRepository->changeStatus($id, $status);
    }
}
