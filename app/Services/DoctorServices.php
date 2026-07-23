<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Doctor;
use App\Exports\DoctorsExport;
use App\Repositories\DoctorRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class DoctorServices implements Doctor
{

    public function __construct(
        protected DoctorRepository $doctorRepository,
    ) {}

    public function create(array $data): Model
    {

        return $this->doctorRepository->create($data);
    }

    public function edit(string $id, array $data): Model
    {
        return $this->doctorRepository->edit($data);
    }

    public function consultById(string $id): ?Model
    {
        return $this->doctorRepository->consultById($id);
    }

    public function consultByIdentification(string $identification): ?Model
    {
        return $this->doctorRepository->consultByIdentification($identification);
    }

    public function deleteById(string $id): void
    {
        $this->doctorRepository->deleteById($id);
    }

    public function consultAll(): Collection
    {
        return $this->doctorRepository->consultAll();
    }

    public function filtrar(array $filtros): LengthAwarePaginator
    {
        return $this->doctorRepository->filtrar($filtros, $filtros["itemsPerPage"]);
    }

    public function filterWithoutPaginate(array $filtros): Collection
    {
        return $this->doctorRepository->filterWithoutPaginate($filtros);
    }

    public function exportExcel(array $filtros): DoctorsExport
    {
        $query = $this->doctorRepository->builerPaginate($filtros);
        return new DoctorsExport($query);
    }
}
