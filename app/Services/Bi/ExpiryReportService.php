<?php

declare(strict_types=1);

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
        $lossAnalysis = $this->repository->getRealLossAnalysis($filters);

        return [
            'horizon'       => $this->repository->getExpiryHorizon($filters),
            'loss_analysis' => $lossAnalysis,
            'overstock'     => $this->processOverstockData($this->repository->getOverstockWarning($filters)),
            'kpis'          => $this->calculateKpis($filters, $lossAnalysis),
        ];
    }

    private function processOverstockData(array $data): array
    {
        $processed = array_map(function ($item) {
            $mesesRestantes  = max(0, $item['meses_restantes']);
            $ventaProyectada = $item['venta_mensual_promedio'] * $mesesRestantes;

            // Usar el campo precalculado en SQL si existe; sino calcular en PHP como fallback
            if (isset($item['unidades_en_riesgo']) && $item['unidades_en_riesgo'] !== null) {
                $excedente = (float) $item['unidades_en_riesgo'];
            } else {
                $excedente = max(0, $item['stock_actual'] - $ventaProyectada);
            }

            $item['excedente_proyectado'] = round($excedente, 2);
            $item['costo_excedente']      = round($excedente * $item['unit_cost'], 2);

            // Etiqueta "Sobrestock en Riesgo" — se incluye solo cuando hay unidades que se perderán
            if ($excedente > 0) {
                $unidades = (int) ceil($excedente);
                $item['risk_label']       = "Sobrestock en Riesgo: {$unidades} " . ($unidades === 1 ? 'unidad' : 'unidades');
                $item['risk_label_short'] = "{$unidades} en riesgo";
                $item['has_overstock_risk'] = true;
            } else {
                $item['risk_label']       = null;
                $item['risk_label_short'] = null;
                $item['has_overstock_risk'] = false;
            }

            // Semaforización por días restantes al vencimiento
            $daysToExpiry = Carbon::now()->diffInDays(Carbon::parse($item['expiration_date']), false);
            if ($daysToExpiry < 0) {
                $item['status'] = 'vencido';
                $item['color']  = 'error';
            } elseif ($daysToExpiry <= 90) {
                $item['status'] = 'critico';
                $item['color']  = 'error';
            } elseif ($daysToExpiry <= 180) {
                $item['status'] = 'moderado';
                $item['color']  = 'warning';
            } else {
                $item['status'] = 'estable';
                $item['color']  = 'success';
            }

            return $item;
        }, $data);

        usort($processed, fn($a, $b) => $b['costo_excedente'] <=> $a['costo_excedente']);

        return $processed;
    }

    private function calculateKpis(array $filters, array $lossData): array
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $historicalLoss = collect($lossData)->firstWhere('month', $currentMonth);
        
        $currentExpired = $this->repository->getCurrentExpiredStock($filters);

        return [
            // El usuario quiere ver lo que sigue en inventario que ya venció este mes
            'total_units_expired_month' => $currentExpired['total_units'] + ($historicalLoss['total_units'] ?? 0),
            'total_cost_merma_month' => $currentExpired['total_value'] + ($historicalLoss['total_cost'] ?? 0),
            'hist_units' => $historicalLoss['total_units'] ?? 0,
            'hist_cost' => $historicalLoss['total_cost'] ?? 0,
            'current_inv_expired_units' => $currentExpired['total_units'],
            'current_inv_expired_value' => $currentExpired['total_value'],
        ];
    }
}
