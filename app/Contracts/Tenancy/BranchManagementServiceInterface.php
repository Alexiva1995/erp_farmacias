<?php

declare(strict_types=1);

namespace App\Contracts\Tenancy;

use App\Models\Tenant;

interface BranchManagementServiceInterface
{
    /**
     * Lista las sucursales pertenecientes al grupo corporativo del tenant dado.
     *
     * @param string $currentTenantId
     * @return array<int, array<string, mixed>>
     */
    public function listGroupBranches(string $currentTenantId): array;

    /**
     * Crea una nueva sucursal dependiente de una Casa Matriz.
     *
     * @param string $parentTenantId
     * @param array<string, mixed> $data
     * @return Tenant
     */
    public function createBranchForPharmacy(string $parentTenantId, array $data): Tenant;

    /**
     * Actualiza la información operativa de una sucursal.
     *
     * @param string $parentTenantId
     * @param string $branchId
     * @param array<string, mixed> $data
     * @return Tenant
     */
    public function updateBranch(string $parentTenantId, string $branchId, array $data): Tenant;

    /**
     * Cambia el estado activo/inactivo de una sucursal.
     *
     * @param string $parentTenantId
     * @param string $branchId
     * @return Tenant
     */
    public function toggleBranchStatus(string $parentTenantId, string $branchId): Tenant;
}
