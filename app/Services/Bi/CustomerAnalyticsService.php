<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Contracts\CustomerAnalytics as CustomerAnalyticsRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerAnalyticsService
{
    public function __construct(
        protected CustomerAnalyticsRepository $repository
    ) {}

    public function getDashboardData(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // 1. KPIs Base
        $kpis = $this->repository->getKpis($filters);
        
        // 2. CRR (Customer Retention Rate)
        // Definición: Clientes que compraron en el periodo anterior y volvieron en este.
        $crr = $this->calculateCRR($startDate, $endDate);
        $kpis['crr'] = round($crr, 2);

        // 3. Churn Rate (No han venido en 90 días)
        $kpis['churn_rate'] = $this->calculateChurnRate();

        // 4. Growth & Acquisition
        $growth = $this->repository->getGrowthData($filters);
        
        // 5. Segmentación por Valor
        $segmentation = $this->repository->getValueSegmentation($filters);

        // 6. Frecuencia de Compra
        $frequency = $this->repository->getFrequencyData($filters);

        // 7. Cohortes
        $cohorts = $this->repository->getCohortData($filters);
        $formattedCohorts = $this->formatCohorts($cohorts);

        // 8. RFM (Top en Riesgo)
        $rfm = $this->repository->getRfmData($filters);
        $atRisk = $this->calculateAtRisk($rfm);

        return [
            'kpis' => $kpis,
            'growth' => $growth,
            'segmentation' => $segmentation,
            'frequency' => $frequency,
            'cohorts' => $formattedCohorts,
            'at_risk' => $atRisk,
        ];
    }

    private function calculateCRR($startDate, $endDate): float
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = $start->diffInDays($end) + 1;

        $previousStart = (clone $start)->subDays($days);
        $previousEnd = (clone $start)->subDay();

        // Clientes al inicio del periodo (que compraron en el periodo anterior)
        $clientsInitial = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$previousStart->format('Y-m-d'), $previousEnd->format('Y-m-d')])
            ->distinct('client_id')
            ->count('client_id');

        if ($clientsInitial === 0) return 0;

        // Clientes al final del periodo (que compraron en el periodo anterior Y en el actual)
        $clientsEnd = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('client_id', function($query) use ($previousStart, $previousEnd) {
                $query->select('client_id')
                    ->from('orders')
                    ->where('status', 'Completed')
                    ->whereBetween('order_date', [$previousStart->format('Y-m-d'), $previousEnd->format('Y-m-d')]);
            })
            ->distinct('client_id')
            ->count('client_id');

        return ($clientsEnd / $clientsInitial) * 100;
    }

    private function calculateChurnRate(): float
    {
        $threeMonthsAgo = now()->subDays(90)->format('Y-m-d');
        
        // Total de clientes activos (que han comprado alguna vez)
        $totalActive = DB::table('orders')
            ->where('status', 'Completed')
            ->distinct('client_id')
            ->count('client_id');

        if ($totalActive === 0) {
            return 0.0;
        }

        // Conteo directo en base de datos de clientes que no han comprado en 90 días
        $churned = DB::table('orders')
            ->select('client_id')
            ->where('status', 'Completed')
            ->groupBy('client_id')
            ->havingRaw('MAX(order_date) < ?', [$threeMonthsAgo])
            ->count();

        return ($churned / $totalActive) * 100;
    }

    private function formatCohorts(array $data): array
    {
        $matrix = [];
        foreach ($data as $row) {
            if (!isset($matrix[$row->cohort_month])) {
                $matrix[$row->cohort_month] = [];
            }
            $matrix[$row->cohort_month][$row->month_number] = $row->active_clients;
        }

        // Convertir a porcentajes basados en el mes 0
        $result = [];
        foreach ($matrix as $month => $values) {
            $initial = $values[0] ?? 1;
            $row = ['month' => $month, 'initial' => $initial, 'data' => []];
            foreach ($values as $mNum => $count) {
                $row['data'][$mNum] = [
                    'count' => $count,
                    'percentage' => round(($count / $initial) * 100, 1)
                ];
            }
            $result[] = $row;
        }

        return $result;
    }

    private function calculateAtRisk(array $rfm): array
    {
        // La consulta de la base de datos ya viene filtrada, ordenada y limitada.
        return $rfm;
    }
}
