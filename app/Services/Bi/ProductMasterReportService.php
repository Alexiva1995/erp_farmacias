<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Contracts\Repositories\ProductMasterReportRepositoryInterface;
use Illuminate\Support\Collection;

class ProductMasterReportService
{
    protected $repository;

    public function __construct(ProductMasterReportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getDashboardData(array $filters): array
    {
        // Cuadrante 1: Rendimiento (TOP 10 limitado directamente desde SQL)
        $topByVolume = $this->repository->getPerformanceData($filters, 10, 'total_sold');
        $topByRevenue = $this->repository->getPerformanceData($filters, 10, 'total_revenue');
        $labRanking = $this->repository->getLaboratoryRanking($filters);
        
        // Pareto KPI calculado eficientemente
        $pareto = $this->repository->getParetoStats($filters);

        // Cuadrante 2: Riesgo
        $abcSummary = $this->repository->getAbcSummary($filters);

        // Cuadrante 4: Abastecimiento (Agregaciones directas de SQL)
        $supplyStats = $this->repository->getSupplyStats($filters);

        return [
            'quadrant1' => [
                'top_volume' => $topByVolume,
                'top_revenue' => $topByRevenue,
                'lab_ranking' => $labRanking,
                'pareto' => $pareto
            ],
            'quadrant2' => [
                'abc' => $abcSummary,
                'cross_selling' => null, // Cargado bajo demanda mediante AJAX/peticiones separadas para no sobrecargar
            ],
            'quadrant4' => [
                'out_of_stock' => $supplyStats['out_of_stock'],
                'critical_stock' => $supplyStats['critical_stock'],
                'avg_inventory_days' => $supplyStats['avg_inventory_days']
            ]
        ];
    }

    public function getTrendData(array $filters): Collection
    {
        return $this->repository->getTrendComparison($filters);
    }

    public function getCrossSellingData(array $filters)
    {
        return $this->repository->getCrossSellingData($filters);
    }

    public function getRankingsData(array $filters)
    {
        return $this->repository->getRankingsData($filters);
    }
}
