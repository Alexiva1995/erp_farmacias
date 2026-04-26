<?php

namespace App\Services\Bi;

use App\Contracts\Repositories\ExpiryReportRepositoryInterface;
use Carbon\Carbon;

class ExpiryReportService
{
    protected $repository;

    public function __construct(ExpiryReportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getDashboardData(array $filters): array
    {
        return [
            'horizon' => $this->repository->getExpiryHorizon($filters),
            'annual_trend' => $this->repository->getAnnualTrend($filters),
            'loss_analysis' => $this->repository->getRealLossAnalysis($filters),
            'risk_inventory' => $this->repository->getRiskInventory($filters),
            'overstock' => $this->processOverstockData($this->repository->getOverstockWarning($filters)),
            'kpis' => $this->calculateKpis($filters)
        ];
    }

    private function processOverstockData(array $data): array
    {
        $processed = array_map(function ($item) {
            $mesesRestantes = max(0, $item['meses_restantes']);
            $ventaProyectada = $item['venta_mensual_promedio'] * $mesesRestantes;
            $excedente = max(0, $item['stock_actual'] - $ventaProyectada);
            
            $item['excedente_proyectado'] = round($excedente, 2);
            $item['costo_excedente'] = round($excedente * $item['unit_cost'], 2);
            
            // Semaforización
            $daysToExpiry = Carbon::now()->diffInDays(Carbon::parse($item['expiration_date']), false);
            if ($daysToExpiry < 0) {
                $item['status'] = 'vencido';
                $item['color'] = 'error';
            } elseif ($daysToExpiry <= 90) {
                $item['status'] = 'critico';
                $item['color'] = 'error';
            } elseif ($daysToExpiry <= 180) {
                $item['status'] = 'moderado';
                $item['color'] = 'warning';
            } else {
                $item['status'] = 'estable';
                $item['color'] = 'success';
            }

            return $item;
        }, $data);

        usort($processed, fn($a, $b) => $b['costo_excedente'] <=> $a['costo_excedente']);

        return $processed;
    }

    private function calculateKpis(array $filters): array
    {
        $lossData = $this->repository->getRealLossAnalysis($filters);
        $currentMonth = Carbon::now()->format('Y-m');
        
        $currentMonthLoss = collect($lossData)->firstWhere('month', $currentMonth);

        return [
            'total_units_expired_month' => $currentMonthLoss['total_units'] ?? 0,
            'total_cost_merma_month' => $currentMonthLoss['total_cost'] ?? 0,
            // Agregaremos más KPIs según sea necesario
        ];
    }
}
