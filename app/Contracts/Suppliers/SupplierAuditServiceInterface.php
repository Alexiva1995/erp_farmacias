<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

interface SupplierAuditServiceInterface
{
    /**
     * Obtiene la auditoría de un proveedor cruzando todos los tenants registrados
     *
     * @param int|null $supplierId ID o filtro del proveedor (por defecto Vickmedicals = 1016)
     * @param string|null $month Mes de análisis en formato Y-m (por defecto mes actual)
     * @return array
     */
    public function getSupplierMultiTenantAudit(?int $supplierId = null, ?string $month = null): array;

    /**
     * Lista de droguerías disponibles para seleccionar en el dashboard
     *
     * @return array
     */
    public function getAvailableSuppliers(): array;
}
