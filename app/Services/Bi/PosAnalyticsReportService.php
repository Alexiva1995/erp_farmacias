<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Repositories\Bi\PosAnalyticsReportRepository;

class PosAnalyticsReportService
{
    public function __construct(
        protected PosAnalyticsReportRepository $repository
    ) {}

    public function getPosDashboard(array $filters): array
    {
        $kpis = $this->repository->getKpis($filters);
        $temporal = $this->repository->getTemporalAnalysis($filters);
        $segmentation = $this->repository->getSegmentation($filters);

        $dailySeries = [
            'series' => [
                [
                    'name' => 'Ventas Totales',
                    'data' => collect($temporal['daily_focus'])->pluck('total_revenue')->map(fn($v) => round($v, 2))->toArray()
                ]
            ],
            'categories' => collect($temporal['daily_focus'])->pluck('day_name')->toArray()
        ];

        // Calcular porcentajes y montos por franja horaria
        $totalHourlyCount = collect($temporal['hourly_slots'])->sum('count');
        $hourlySeries = [
            'series' => [
                [
                    'name' => 'Distribución',
                    'data' => collect($temporal['hourly_slots'])->map(function($slot) use ($totalHourlyCount, $temporal) {
                        $h = (int)$slot->hour;
                        return [
                            'x' => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00',
                            'y' => $totalHourlyCount > 0 ? round(($slot->count / $totalHourlyCount) * 100, 1) : 0,
                            'revenue' => round($slot->revenue, 2),
                            'top_seller' => isset($temporal['top_sellers'][$h]) ? [
                                'seller_name' => collect(explode(' ', $temporal['top_sellers'][$h]->seller_name))->take(2)->implode(' '),
                                'revenue' => round($temporal['top_sellers'][$h]->revenue, 2)
                            ] : (isset($temporal['top_sellers']["$h"]) ? [
                                'seller_name' => collect(explode(' ', $temporal['top_sellers']["$h"]->seller_name))->take(2)->implode(' '),
                                'revenue' => round($temporal['top_sellers']["$h"]->revenue, 2)
                            ] : null)
                        ];
                    })->toArray()
                ]
            ]
        ];

        return [
            'kpis' => $kpis,
            'charts' => [
                'daily_focus' => $dailySeries,
                'hourly_distribution' => $hourlySeries,
            ],
            'segmentation' => [
                'units' => [
                    'labels' => array_keys($segmentation['units']),
                    'series' => array_values($segmentation['units'])
                ],
                'monetary' => [
                    'labels' => array_keys($segmentation['monetary']),
                    'series' => array_values($segmentation['monetary'])
                ]
            ]
        ];
    }
}
