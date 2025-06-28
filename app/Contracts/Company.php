<?php

namespace App\Contracts;

use App\Contracts\Methods\ConsultAll;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface Company
{
    public function create(array $data): Model;

    public function edit(array $data): Model;

    public function consultAll(): Collection;

    public function consultById(string $id): Model|null;

    public function deleteById(string $id): void;

    public function filtrar(array $filtros): LengthAwarePaginator;
}
