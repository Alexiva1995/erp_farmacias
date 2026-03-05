<?php

namespace App\Services;

use App\Contracts\PurchaseOrder;
use App\Models\AutoOrder;
use App\Repository\AutoOrderDetailsRepository;
use App\Repository\AutoOrdersRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderServices implements PurchaseOrder
{
  public function __construct(
    protected AutoOrdersRepository $autoOrdersRepository,
    protected AutoOrderDetailsRepository $autoOrderDetailsRepository,
  ) {
  }

  public function baseQuery(): Builder
  {
    return $this->autoOrdersRepository->baseQuery();
  }

  public function applyFilters(Builder $query, array $filters = []): mixed
  {
    return $this->autoOrdersRepository->applyFilters($query, $filters);
  }

  public function getAll(array $data): LengthAwarePaginator
  {
    return $this->autoOrdersRepository->getAll($data);
  }

  public function getHistory(array $data): mixed
  {
    $data["itemsPerPage"] ??= 10;

    return $this->autoOrdersRepository->getHistory($data);
  }

  public function delete(AutoOrder $autoOrder): bool
  {
    return $this->autoOrdersRepository->delete($autoOrder);
  }

  public function update(AutoOrder $autoOrder, array $data): array
  {
    return $this->autoOrdersRepository->update($autoOrder, $data);
  }

  public function getExportableData(AutoOrder $autoOrder): Collection
  {
    return $this->autoOrdersRepository->getExportableData($autoOrder);
  }

  public function getStats(array $data): array
  {
    return $this->autoOrdersRepository->getStats($data);
  }

  public function confirmSent(AutoOrder $autoOrder): bool
  {
    return $this->autoOrdersRepository->confirmSent($autoOrder);
  }
}
