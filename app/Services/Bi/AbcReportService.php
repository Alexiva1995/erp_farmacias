<?php

declare(strict_types=1);

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

        // 1. Preparar campos bases (Márgenes, Variaciones, GMROI)
        $data->transform(function ($item) use ($daysInPeriod) {
            $item->total_sales = (float) $item->total_sales;
            $item->total_cost = (float) $item->total_cost;
            $item->sold_units = (float) $item->sold_units;
            $item->current_stock = (float) $item->current_stock;
            $item->last_cost = (float) $item->last_cost;
            
            // Margen absolutos y relativos
            $item->margin_amount = $item->total_sales - $item->total_cost;
            $item->margin_percentage = $item->total_sales > 0 
                ? ($item->margin_amount / $item->total_sales) * 100 
                : 0;

            // Valor de Inventario Actual (Capital Atrapado)
            $item->inventory_value = $item->current_stock * $item->last_cost;

            // GMROI Anualizado (%)
            // Proyectamos la rentabilidad del periodo a 365 días para una métrica estándar
            $item->gmroi = $item->inventory_value > 0 
                ? ($item->margin_amount / $item->inventory_value) * (365 / $daysInPeriod) * 100
                : ($item->margin_amount > 0 ? 9999 : 0);

            // Días de Inventario usando el promedio mensual precalculado del producto (sales_average / 30)
            // Este campo es independiente del filtro de fechas y refleja el comportamiento real histórico
            $monthlyAvg = (float) ($item->sales_average ?? 0);
            $dailyAvgFromProduct = $monthlyAvg / 30;
            $item->inventory_days = $dailyAvgFromProduct > 0
                ? (float) $item->current_stock / $dailyAvgFromProduct
                : ($item->current_stock > 0 ? 9999 : 0);

            // Coeficiente de Variación (CV)
            $item->cv = $item->avg_daily_sales > 0 
                ? (float) ($item->std_dev_sales / $item->avg_daily_sales) 
                : 999; 

            // Determinar XYZ
            // Regla de Relevancia: Menos de 3 unidades se considera impredecible (Z) por falta de muestra
            if ($item->sold_units < 3) {
                $item->class_rotation = 'Z';
            } elseif ($item->cv < 0.5) {
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

        // 5. Aplicar Filtros de Análisis Especializados
        $analysisType = $filtros['analysis_type'] ?? 'all';
        if ($analysisType === 'dead_stock') {
            // Stock Muerto: Tiene stock pero no se vendió nada en el periodo
            $data = $data->filter(function ($item) {
                return $item->sold_units <= 0 && $item->current_stock > 0;
            });
        } elseif ($analysisType === 'star_products') {
            // Productos Estrella: Ventas A y Margen A
            $data = $data->filter(function ($item) {
                return $item->class_sales === 'A' && $item->class_margin === 'A';
            });
        }

        // 6. Aplicar Filtros Ad-hoc (ROI y Stock)
        if (isset($filtros['min_gmroi'])) {
            $minRoi = (float) $filtros['min_gmroi'];
            $data = $data->filter(fn($item) => $item->gmroi >= $minRoi);
        }

        if (isset($filtros['stock_filter']) && $filtros['stock_filter'] !== 'all') {
            if ($filtros['stock_filter'] === 'with_stock') {
                // Solo productos con existencias
                $data = $data->filter(fn($item) => $item->current_stock > 0);
            } elseif ($filtros['stock_filter'] === 'out_of_stock') {
                // Solo productos agotados
                $data = $data->filter(fn($item) => $item->current_stock <= 0);
            }
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
            
            // Guardar el aporte individual (Ej: "vende el 2% del total de la farmacia")
            $pctField = $assignField === 'class_sales' ? 'contribution_sales_pct' : 'contribution_margin_pct';
            $item->{$pctField} = ($item->{$metricField} / $totalSum) * 100;

            // Pareto puro: 80/15/5 sin umbrales mínimos de dinero
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
