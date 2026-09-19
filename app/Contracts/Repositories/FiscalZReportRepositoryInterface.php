<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\FiscalZReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FiscalZReportRepositoryInterface
{
    /**
     * Obtiene el listado paginado y filtrado de Reportes Z.
     */
    public function getFilteredPaginated(array $filters, int $perPage = 10, ?string $sortBy = 'report_date', string $orderBy = 'desc'): LengthAwarePaginator;

    /**
     * Obtiene los totales agregados para los filtros aplicados.
     */
    public function getSummaryStats(array $filters): array;

    /**
     * Busca un Reporte Z por su ID.
     */
    public function findById(int $id): ?FiscalZReport;

    /**
     * Busca un Reporte Z por su número.
     */
    public function findByNumber(int $number): ?FiscalZReport;

    /**
     * Busca un Reporte Z por su fecha.
     */
    public function findByDate(string $date): ?FiscalZReport;

    /**
     * Obtiene el último número de Reporte Z registrado.
     */
    public function getLastReportNumber(): ?int;

    /**
     * Crea o actualiza un Reporte Z.
     */
    public function updateOrCreateByDate(string $date, array $data): FiscalZReport;

    /**
     * Elimina un Reporte Z por su número.
     */
    public function deleteByNumber(int $number): bool;

    /**
     * Elimina un Reporte Z por su fecha.
     */
    public function deleteByDate(string $date): bool;
}
