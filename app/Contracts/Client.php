<?php

namespace App\Contracts;

use App\Exports\ClientsExport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface Client
{

    public function create(array $data): Model;

    public function edit(array $data): Model;

    public function consultAll(): Collection;

    public function consultById(string $id): Model|null;

    public function consultByIdentification(string $identification): Model|null;

    public function deleteById(string $id): void;

    public function filtrar(array $filtros): LengthAwarePaginator;
    public function pending(array $filters): LengthAwarePaginator;

    public function filterWithoutPaginate(array $filtros): Collection;

    public function exportExcel(array $filtros): ClientsExport;

    public function updateCompany(int $client_id, int $company_id, bool $status): ?Model;

    public function bulkCleanupInvalid(): int;
}
