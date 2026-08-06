<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Repositories\Bi\LaboratoryMasterReportRepository;
use Illuminate\Support\Collection;

class LaboratoryMasterReportService
{
    public function __construct(
        protected LaboratoryMasterReportRepository $repository
    ) {}

    public function getCatalogs(bool $groupByCorporate = false): Collection
    {
        return $this->repository->getCatalogs($groupByCorporate);
    }

    public function getDashboardSummary(array $filters): array
    {
        return [
            'rankings' => [
                'by_units' => $this->repository->getRankings('total_units', 1, $filters),
                'by_revenue' => $this->repository->getRankings('total_revenue', 1, $filters),
                'by_stock' => $this->repository->getRankings('total_stock', 1, $filters),
            ],
            'trends' => $this->repository->getTrendData($filters),
            'stock_on_hand' => $this->repository->getStockOnHand(array_merge($filters, ['metric' => 'inventory_value'])),
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

        // Identificar Grupos Compartidos
        $groupsA = collect($dataA['group_performance']);
        $groupsB = collect($dataB['group_performance']);

        $sharedGroupIds = $groupsA->pluck('id')->intersect($groupsB->pluck('id'));

        $sharedComparisons = $sharedGroupIds->map(function ($groupId) use ($groupsA, $groupsB, $dataA, $dataB) {
            $gA = $groupsA->firstWhere('id', $groupId);
            $gB = $groupsB->firstWhere('id', $groupId);
            
            $productsA = collect($dataA['top_products'])->where('group_id', $groupId)->values();
            $productsB = collect($dataB['top_products'])->where('group_id', $groupId)->values();

            $totalRev = $gA->revenue + $gB->revenue;

            return [
                'group_id' => $groupId,
                'name' => $gA->name,
                'revenue_a' => $gA->revenue,
                'revenue_b' => $gB->revenue,
                'units_a' => $gA->units,
                'units_b' => $gB->units,
                'share_a' => $totalRev > 0 ? round(($gA->revenue / $totalRev) * 100, 1) : 0,
                'share_b' => $totalRev > 0 ? round(($gB->revenue / $totalRev) * 100, 1) : 0,
                'products_a' => $productsA,
                'products_b' => $productsB
            ];
        })->values();

        // Calcular Market Share Relativo entre los dos (Total)
        $revA = collect($dataA['top_products'])->sum('revenue');
        $revB = collect($dataB['top_products'])->sum('revenue');
        $total = $revA + $revB;

        return [
            'lab_a' => array_merge(['details' => $dataA], [
                'share_relative' => $total > 0 ? round(($revA / $total) * 100, 2) : 0
            ]),
            'lab_b' => array_merge(['details' => $dataB], [
                'share_relative' => $total > 0 ? round(($revB / $total) * 100, 2) : 0
            ]),
            'shared_groups' => $sharedComparisons
        ];
    }
}
