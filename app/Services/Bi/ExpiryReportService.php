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
            $daysToExpiry = (int) Carbon::now()->diffInDays(Carbon::parse($item['expiration_date']), false);
            $item['days_to_expiry'] = $daysToExpiry;

            if ($daysToExpiry < 0) {
                $item['status'] = 'vencido';
                $item['color']  = 'error';
            } elseif ($daysToExpiry <= 90) {
                $item['status'] = 'critico';
                $item['color']  = 'error';
            } elseif ($daysToExpiry <= 180 || $excedente > 0) {
                // Si tiene excedente en riesgo pero vence en >180 días, se clasifica mínimo como moderado
                $item['status'] = 'moderado';
                $item['color']  = 'warning';
            } else {
                $item['status'] = 'estable';
                $item['color']  = 'success';
            }

            // Días de Cobertura (DIO) a nivel de producto/lote
            $dailySales = ((float) ($item['venta_mensual_promedio'] ?? 0)) / 30;
            $item['dio'] = $dailySales > 0 ? (int) round(((float) $item['stock_actual']) / $dailySales) : 999;

            return $item;
        }, $data);

        usort($processed, fn($a, $b) => $b['costo_excedente'] <=> $a['costo_excedente']);

        return $processed;
    }

    private function calculateKpis(array $filters, array $lossData): array
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $prevMonth = Carbon::now()->subMonth()->format('Y-m');

        $historicalLossCurrent = collect($lossData)->firstWhere('month', $currentMonth);
        $historicalLossPrev = collect($lossData)->firstWhere('month', $prevMonth);

        $currentCost = (float) ($historicalLossCurrent['total_cost'] ?? 0);
        $prevCost    = (float) ($historicalLossPrev['total_cost'] ?? 0);
        $costTrendPct = $prevCost > 0 ? round((($currentCost - $prevCost) / $prevCost) * 100, 1) : 0.0;

        $currentUnits = (float) ($historicalLossCurrent['total_units'] ?? 0);
        $prevUnits    = (float) ($historicalLossPrev['total_units'] ?? 0);
        $unitsTrendPct = $prevUnits > 0 ? round((($currentUnits - $prevUnits) / $prevUnits) * 100, 1) : 0.0;

        $currentExpired = $this->repository->getCurrentExpiredStock($filters);

        return [
            // El usuario quiere ver lo que sigue en inventario que ya venció este mes + historial
            'total_units_expired_month' => $currentExpired['total_units'] + ($historicalLossCurrent['total_units'] ?? 0),
            'total_cost_merma_month'    => $currentExpired['total_value'] + ($historicalLossCurrent['total_cost'] ?? 0),
            'hist_units'                => $historicalLossCurrent['total_units'] ?? 0,
            'hist_cost'                 => $historicalLossCurrent['total_cost'] ?? 0,
            'current_inv_expired_units' => $currentExpired['total_units'],
            'current_inv_expired_value' => $currentExpired['total_value'],
            'cost_trend_pct'            => $costTrendPct,
            'units_trend_pct'           => $unitsTrendPct,
        ];
    }
}
