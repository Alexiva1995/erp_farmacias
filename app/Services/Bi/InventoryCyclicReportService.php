<?php

namespace App\Services\Bi;

use App\Repositories\Bi\InventoryCyclicReportRepository;

class InventoryCyclicReportService
{
    public function __construct(
        protected InventoryCyclicReportRepository $repository
    ) {}

    public function getInventoryDashboard(array $filters): array
    {
        $kpis = $this->repository->getKpis($filters);
        $trends = $this->repository->getTrends($filters);
        $deviations = $this->repository->getTopDeviations($filters);
        $categoryDeviations = $this->repository->getCategoryDeviation($filters);
        $substitutions = $this->repository->getCodeCrossing($filters);

        // Formatear Tendencias para ApexCharts
        $trendChart = [
            'series' => [
                [
                    'name' => 'Faltantes',
                    'data' => collect($trends)->pluck('missing')->map(fn($v) => (float)$v)->toArray()
                ],
                [
                    'name' => 'Sobrantes',
                    'data' => collect($trends)->pluck('surplus')->map(fn($v) => (float)$v)->toArray()
                ]
            ],
            'categories' => collect($trends)->pluck('month')->toArray(),
            'financial_series' => [
                [
                    'name' => 'Impacto Neto ($)',
                    'data' => collect($trends)->pluck('financial_impact')->map(fn($v) => round((float)$v, 2))->toArray()
                ]
            ]
        ];

        return [
            'kpis' => $kpis,
            'trends' => $trendChart,
            'deviations' => [
                'top_missing' => [
                    'series' => [['name' => 'Unidades', 'data' => collect($deviations['missing'])->pluck('discrepancy')->map(fn($v) => abs($v))->toArray()]],
                    'categories' => collect($deviations['missing'])->pluck('name')->toArray()
                ],
                'top_surplus' => [
                    'series' => [['name' => 'Unidades', 'data' => collect($deviations['surplus'])->pluck('discrepancy')->toArray()]],
                    'categories' => collect($deviations['surplus'])->pluck('name')->toArray()
                ],
                'categories' => [
                    'series' => collect($categoryDeviations)->pluck('total_deviation')->map(fn($v) => (float)$v)->toArray(),
                    'labels' => collect($categoryDeviations)->pluck('name')->toArray()
                ]
            ],
            'substitutions' => $substitutions
        ];
    }
}
