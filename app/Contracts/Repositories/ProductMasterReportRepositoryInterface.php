<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

interface ProductMasterReportRepositoryInterface
{
    /**
     * Obtiene el rendimiento de productos (opcionalmente ordenado y limitado)
     */
    public function getPerformanceData(array $filters, ?int $limit = null, ?string $sortBy = null): Collection;

    /**
     * Obtiene estadísticas de Pareto de utilidad de forma eficiente
     */
    public function getParetoStats(array $filters): array;

    /**
     * Obtiene el ranking de laboratorios por rentabilidad
     */
    public function getLaboratoryRanking(array $filters): Collection;

    /**
     * Obtiene el resumen ABC (Conteo de productos y valor atrapado)
     */
    public function getAbcSummary(array $filters): Collection;

    /**
     * Obtiene datos de Cross-selling (productos comprados juntos)
     */
    public function getCrossSellingData(array $filters);

    /**
     * Obtiene métricas agregadas de abastecimiento (Out of stock y Días de inventario)
     */
    public function getSupplyStats(array $filters): array;

    /**
     * Obtiene tendencias de Ventas vs Compras para comparación
     */
    public function getTrendComparison(array $filters): Collection;

    /**
     * Obtiene rankings de productos paginados
     */
    public function getRankingsData(array $filters);
}

