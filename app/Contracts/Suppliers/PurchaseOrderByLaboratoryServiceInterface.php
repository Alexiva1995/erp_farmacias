<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PurchaseOrderByLaboratoryServiceInterface
{
    /**
     * Obtiene el listado paginado de órdenes agrupadas por laboratorio.
     */
    public function getAggregatedLaboratories(array $filters): LengthAwarePaginator;

    /**
     * Obtiene los ítems detallados de un laboratorio específico.
     */
    public function getLaboratoryDetails(int|string $laboratoryId, array $filters): LengthAwarePaginator;

    /**
     * Obtiene los KPIs estadísticos consolidados por laboratorio.
     */
    public function getStats(array $filters): array;

    /**
     * Obtiene los datos para exportación de un laboratorio.
     */
    public function getExportData(int|string $laboratoryId, array $filters): Collection;
}
