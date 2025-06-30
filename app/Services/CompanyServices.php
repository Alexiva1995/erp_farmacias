<?php


namespace App\Services;

use App\Contracts\Company;
use App\Repository\CompanyRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyServices implements Company
{

    public function __construct(
        protected CompanyRepository $companyRepository
    ) {}

    public function create(array $data): Model
    {
        return $this->companyRepository->create($data);
    }

    public function edit(array $data): Model
    {
        return $this->companyRepository->edit($data);
    }

    public function consultAll(): Collection
    {
        return $this->companyRepository->consultAll();
    }

    public function consultById(string $id): ?Model
    {
        return $this->companyRepository->consultById($id);
    }

    public function deleteById(string $id): void
    {
        $this->companyRepository->deleteById($id);
    }

    public function filtrar(array $filtros): LengthAwarePaginator
    {
        return $this->companyRepository->filtrar($filtros, $filtros["itemsPerPage"]);
    }
}
