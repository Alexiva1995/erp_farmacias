<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

interface ProductMasterReportRepositoryInterface
{
    /**
     * Obtiene el rendimiento de productos (Top Volumen y Margen)
     */
    public function getPerformanceData(array $filters): Collection;

    /**
     * Obtiene el ranking de laboratorios por rentabilidad
     */
    public function getLaboratoryRanking(array $filters): Collection;

    /**
     * Obtiene el resumen ABC (Conteo de productos y valor atrapado)
     */
    public function getAbcSummary(array $filters): Collection;

    /**
     * Obtiene los vencimientos en cubetas de tiempo
     */
    public function getExpirationsData(): Collection;

    /**
     * Obtiene discrepancias de inventario histórico
     */
    public function getInventoryDiscrepancies(array $filters): Collection;

    /**
     * Obtiene métricas de abastecimiento (Out of stock y Días de inventario)
     */
    public function getSupplyIntelligence(array $filters): Collection;

    /**
     * Obtiene tendencias de Ventas vs Compras para comparación
     */
    public function getTrendComparison(array $filters): Collection;
}
