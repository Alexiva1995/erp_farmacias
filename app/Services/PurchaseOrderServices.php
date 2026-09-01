<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PurchaseOrder;
use App\Models\AutoOrder;
use App\Repositories\AutoOrderDetailsRepository;
use App\Repositories\AutoOrdersRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderServices implements PurchaseOrder
{
  public function __construct(
    protected AutoOrdersRepository $autoOrdersRepository,
    protected AutoOrderDetailsRepository $autoOrderDetailsRepository,
    protected \App\Contracts\Suppliers\DronenaEdiServiceInterface $dronenaEdiService,
    protected \App\Contracts\Suppliers\VitalclinicFtpServiceInterface $vitalclinicFtpService,
    protected \App\Contracts\Suppliers\DrocercaFtpServiceInterface $drocercaFtpService,
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
    $supplier = $autoOrder->supplier ?: \App\Models\Supplier::find($autoOrder->supplier_id);
    
    // Identificar proveedor automatizado (Dronena, Vitalclinic, Drocerca)
    $isDronena = false;
    $isVitalclinic = false;
    $isDrocerca = false;

    if ($supplier) {
      $supplierName = strtoupper($supplier->name);
      if (str_contains($supplierName, 'NENA') || str_contains($supplierName, 'DRONENA')) {
        $isDronena = true;
      } else {
        $hasDronenaFtp = $supplier->connections()->where('host', 'LIKE', '%dronena%')->exists();
        if ($hasDronenaFtp) {
          $isDronena = true;
        }
      }

      if (str_contains($supplierName, 'VITALCLINIC') || str_contains($supplierName, 'VITAL CLINIC')) {
        $isVitalclinic = true;
      } else {
        $hasVitalclinicFtp = $supplier->connections()->where(function ($query) {
          $query->where('host', 'LIKE', '%vitalclinic%')
            ->orWhere('username', 'LIKE', '%vitalclinic%');
        })->exists();
        if ($hasVitalclinicFtp) {
          $isVitalclinic = true;
        }
      }

      if (str_contains($supplierName, 'DROCERCA') || str_contains($supplierName, 'CERCA')) {
        $isDrocerca = true;
      } else {
        $hasDrocercaFtp = $supplier->connections()->where(function ($query) {
          $query->where('host', 'LIKE', '%drocerca%')
            ->orWhere('username', 'LIKE', '%drocerca%')
            ->orWhere('type', 'drocerca_bot');
        })->exists();
        if ($hasDrocercaFtp) {
          $isDrocerca = true;
        }
      }
    }

    if ($isDronena) {
      try {
        $this->dronenaEdiService->sendOrderFtp($autoOrder);
      } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error("[DRONENA EDI] Error transmitiendo pedido automático #{$autoOrder->id}: " . $e->getMessage());
        throw new \Exception("Error al transmitir el pedido a Droguería Nena por FTP: " . $e->getMessage());
      }
    }

    if ($isVitalclinic) {
      try {
        $this->vitalclinicFtpService->sendOrderFtp($autoOrder);
      } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error("[VITALCLINIC FTP] Error transmitiendo pedido automático #{$autoOrder->id}: " . $e->getMessage());
        throw new \Exception("Error al transmitir el pedido a Droguería Vitalclinic por FTP: " . $e->getMessage());
      }
    }

    if ($isDrocerca) {
      try {
        $this->drocercaFtpService->sendOrderFtp($autoOrder);
      } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error("[DROCERCA FTP] Error transmitiendo pedido automático #{$autoOrder->id}: " . $e->getMessage());
        throw new \Exception("Error al transmitir el pedido a Drocerca por FTP: " . $e->getMessage());
      }
    }

    return $this->autoOrdersRepository->confirmSent($autoOrder);
  }

  public function finish(AutoOrder $autoOrder): bool
  {
    return $this->autoOrdersRepository->finish($autoOrder);
  }

  public function rejectPendingDetails(AutoOrder $autoOrder): void
  {
    $this->autoOrdersRepository->rejectPendingDetails($autoOrder);
  }

  public function revertToSent(AutoOrder $autoOrder): bool
  {
    return $this->autoOrdersRepository->revertToSent($autoOrder);
  }
}
