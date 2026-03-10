<?php

namespace App\Services\Bi;

use App\Contracts\Repositories\AbcReportRepositoryInterface;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Clase AbcReportService
 * 
 * Orquesta la transformación de los datos agrupados de ventas y asigna
 * la clasificación ABC (Ventas), ABC (Margen) y XYZ (Variabilidad) a cada producto.
 */
class AbcReportService
{
    public function __construct(
        protected AbcReportRepositoryInterface $repository
    ) {
    }

    /**
     * Obtener el cálculo completo de ABC Multicriterio.
     *
     * @param array $filtros
     * @return Collection
     */
    public function getCalculatedAbcReport(array $filtros): Collection
    {
        $data = $this->repository->getAggregatedData($filtros);

        if ($data->isEmpty()) {
            return collect([]);
        }

        // Configuración de Fechas para días de cálculo
        $start = Carbon::parse($filtros['start_date'] ?? now()->subDays(90)->startOfDay());
        $end = Carbon::parse($filtros['end_date'] ?? now()->endOfDay());
        $daysInPeriod = max(1, $start->diffInDays($end));

        // 1. Preparar campos bases (Márgenes y Variaciones)
        $data->transform(function ($item) use ($daysInPeriod) {
            $item->total_sales = (float) $item->total_sales;
            $item->total_cost = (float) $item->total_cost;
            $item->sold_units = (float) $item->sold_units;
            
            // Margen absolutos y relativos
            $item->margin_amount = $item->total_sales - $item->total_cost;
            $item->margin_percentage = $item->total_sales > 0 
                ? ($item->margin_amount / $item->total_sales) * 100 
                : 0;

            // Días de Inventario = Stock actual / Venta diaria promedio total del periodo
            $avgDailySalesOverPeriod = $item->sold_units / $daysInPeriod;
            $item->inventory_days = $avgDailySalesOverPeriod > 0 
                ? (float) $item->current_stock / $avgDailySalesOverPeriod 
                : 9999; // Representando exceso o falta de venta

            // Coeficiente de Variación (CV) = Desviación / Media
            // NOTA: Media de días con ventas activas. Si no hubo ventas, CV es infinito -> impredecible
            $item->cv = $item->avg_daily_sales > 0 
                ? (float) ($item->std_dev_sales / $item->avg_daily_sales) 
                : 999; 

            // Determinar XYZ
            if ($item->cv < 0.5) {
                $item->class_rotation = 'X';
            } elseif ($item->cv <= 1.0) {
                $item->class_rotation = 'Y';
            } else {
                $item->class_rotation = 'Z';
            }

            return $item;
        });

        // 2. Clasificación ABC por Ventas (Dimensión 1)
        $data = $this->applyAbcClassification($data, 'total_sales', 'class_sales');

        // 3. Clasificación ABC por Margen (Dimensión 2)
        $data = $this->applyAbcClassification($data, 'margin_amount', 'class_margin');

        // 4. Determinar Letra Final Combinada
        $data->transform(function ($item) {
            $item->final_classification = $item->class_sales . $item->class_margin . $item->class_rotation;
            return $item;
        });

        // Aplicar filtro de Letra Final si existe
        if (!empty($filtros['final_classification'])) {
            $filterLetter = strtoupper($filtros['final_classification']);
            $data = $data->filter(function ($item) use ($filterLetter) {
                return $item->final_classification === $filterLetter;
            });
        }

        return $data->values();
    }

    /**
     * Aplica la regla ABC (80-15-5) basada en una métrica específica.
     *
     * @param Collection $data Colección de productos
     * @param string $metricField Campo a usar para el Pareto (ej: total_sales)
     * @param string $assignField Campo donde se guardará la letra (A, B o C)
     * @return Collection
     */
    private function applyAbcClassification(Collection $data, string $metricField, string $assignField): Collection
    {
        // Ordenar de mayor a menor según la métrica
        $sorted = $data->sortByDesc($metricField)->values();
        
        // Suma total de toda la métrica para calcular porcentajes (solo valores positivos, A-C clásico)
        // Valores <= 0 quedan obligados a ser 'C'
        $totalSum = $sorted->filter(fn($i) => $i->{$metricField} > 0)->sum($metricField);

        $runningSum = 0;

        $sorted->transform(function ($item) use ($totalSum, &$runningSum, $metricField, $assignField) {
            if ($item->{$metricField} <= 0 || $totalSum == 0) {
                $item->{$assignField} = 'C';
                return $item;
            }

            $runningSum += $item->{$metricField};
            $accumulatedPercentage = ($runningSum / $totalSum) * 100;

            if ($accumulatedPercentage <= 80) {
                $item->{$assignField} = 'A';
            } elseif ($accumulatedPercentage <= 95) {
                $item->{$assignField} = 'B';
            } else {
                $item->{$assignField} = 'C';
            }

            return $item;
        });

        return $sorted;
    }
}
