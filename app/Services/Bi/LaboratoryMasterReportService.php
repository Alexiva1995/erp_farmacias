<?php

namespace App\Services\Bi;

use App\Repositories\Bi\LaboratoryMasterReportRepository;
use Illuminate\Support\Collection;

class LaboratoryMasterReportService
{
    public function __construct(
        protected LaboratoryMasterReportRepository $repository
    ) {}

    public function getDashboardSummary(array $filters): array
    {
        return [
            'rankings' => [
                'by_units' => $this->repository->getRankings('total_units', 1, $filters),
                'by_revenue' => $this->repository->getRankings('total_revenue', 1, $filters),
            ],
            'trends' => $this->repository->getTrendData($filters),
            'stock_on_hand' => $this->repository->getStockOnHand($filters),
            'profitability' => $this->repository->getProfitability($filters),
        ];
    }

    public function getRankings(string $metric, int $page, array $filters)
    {
        return $this->repository->getRankings($metric, $page, $filters);
    }

    public function getLaboratoryDeepDive(int $labId, array $filters): array
    {
        return $this->repository->getLaboratoryDetails($labId, $filters);
    }

    /**
     * Lógica para Comparativa Lado a Lado (Benchmarking)
     */
    public function getBenchmarking(int $labIdA, int $labIdB, array $filters): array
    {
        $dataA = $this->repository->getLaboratoryDetails($labIdA, $filters);
        $dataB = $this->repository->getLaboratoryDetails($labIdB, $filters);

        // Calcular Market Share Relativo entre los dos
        $revA = $dataA['top_products']->sum('revenue');
        $revB = $dataB['top_products']->sum('revenue');
        $total = $revA + $revB;

        return [
            'lab_a' => array_merge(['details' => $dataA], [
                'share_relative' => $total > 0 ? round(($revA / $total) * 100, 2) : 0
            ]),
            'lab_b' => array_merge(['details' => $dataB], [
                'share_relative' => $total > 0 ? round(($revB / $total) * 100, 2) : 0
            ])
        ];
    }
}
