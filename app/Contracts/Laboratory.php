<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface Laboratory
{

    // public function create(array $data): Model;

    // public function edit(String $id, array $data): Model;

    public function consultAll(): Collection;

    // public function consultById(string $id): Model | null;

    // public function consultByIdentification(string $identification): Model | null;

    // public function deleteById(string $id): void;

    // public function filtrar(array $filtros): LengthAwarePaginator;

    // public function filterWithoutPaginate(array $filtros): Collection;

    // public function exportExcel(array $filtros): DoctorsExport;


}
