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
        // Cuadrante 1: Rendimiento
        $performance = $this->repository->getPerformanceData($filters);
        $topByVolume = $performance->sortByDesc('total_sold')->take(10)->values();
        $topByRevenue = $performance->sortByDesc('total_revenue')->take(10)->values();
        $labRanking = $this->repository->getLaboratoryRanking($filters);
        
        // Pareto KPI
        $totalMargin = $performance->sum('total_margin');
        $marginRunningSum = 0;
        $paretoCount = 0;
        $sortedByMargin = $performance->sortByDesc('total_margin');
        foreach ($sortedByMargin as $item) {
            $marginRunningSum += $item->total_margin;
            $paretoCount++;
            if ($totalMargin > 0 && ($marginRunningSum / $totalMargin) >= 0.8) break;
        }
        $paretoPercent = $performance->count() > 0 ? ($paretoCount / $performance->count()) * 100 : 0;

        // Cuadrante 2: Riesgo
        $abcSummary = $this->repository->getAbcSummary($filters);
        $crossSelling = $this->repository->getCrossSellingData($filters);

        // Cuadrante 4: Abastecimiento
        $supply = $this->repository->getSupplyIntelligence($filters);
        $outOfStockCount = $supply->where('stock', '<=', 0)->count();
        $criticalStockCount = $supply->where('days_remaining', '<', 7)->count();

        return [
            'quadrant1' => [
                'top_volume' => $topByVolume,
                'top_revenue' => $topByRevenue,
                'lab_ranking' => $labRanking,
                'pareto' => [
                    'count' => $paretoCount,
                    'percent' => round($paretoPercent, 2),
                    'total_items' => $performance->count()
                ]
            ],
            'quadrant2' => [
                'abc' => $abcSummary,
                'cross_selling' => $crossSelling,
            ],
            'quadrant4' => [
                'out_of_stock' => $outOfStockCount,
                'critical_stock' => $criticalStockCount,
                'avg_inventory_days' => $supply->avg('days_remaining')
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
