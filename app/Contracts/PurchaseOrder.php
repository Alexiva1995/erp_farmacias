<?php

namespace App\Contracts;

use App\Models\AutoOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

interface PurchaseOrder
{
  public function baseQuery(): Builder;
  public function applyFilters(Builder $query, array $data): mixed;
  public function getAll(array $data): LengthAwarePaginator;
  public function getHistory(array $data): mixed;
  public function delete(AutoOrder $autoOrder): bool;
  public function update(AutoOrder $autoOrder, array $data): array;
  public function getExportableData(AutoOrder $autoOrder): Collection;
  public function getStats(array $data): array;
}
