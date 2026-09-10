<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Contracts\Repositories\AbcReportRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        // Generar una clave de caché única basada en los filtros aplicados
        $cacheKey = 'abc_report_' . md5(json_encode($filtros));

        return Cache::remember($cacheKey, 600, function () use ($filtros) {
            $data = $this->repository->getAggregatedData($filtros);

            if ($data->isEmpty()) {
                return collect([]);
            }

            // Configuración de Fechas para días de cálculo
            $start = Carbon::parse($filtros['start_date'] ?? now()->subDays(90)->startOfDay());
            $end = Carbon::parse($filtros['end_date'] ?? now()->endOfDay());
            $daysInPeriod = max(1, $start->diffInDays($end));

            // Cargar en bloque todos los lotes activos para evaluación FEFO precisa
            $lotsByProduct = \Illuminate\Support\Facades\DB::table('product_lots')
                ->select('product_id', 'quantity', 'expiration_date')
                ->where('quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->orderBy('expiration_date', 'asc')
                ->get()
                ->groupBy('product_id');

            // 1. Preparar campos bases (Márgenes, Variaciones, GMROI y FEFO)
            $data->transform(function ($item) use ($daysInPeriod, $lotsByProduct) {
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

                // Días de Inventario / Cobertura:
                // Se calcula con base en la velocidad de salida diaria (usando el promedio ponderado histórico o el periodo actual)
                $monthlyAvg = (float) ($item->sales_average_weighted ?? $item->sales_average ?? 0);
                $dailyAvgFromProduct = $monthlyAvg / 30;
                $dailyAvgFromPeriod = $daysInPeriod > 0 ? ($item->sold_units / $daysInPeriod) : 0;
                $dailyRunRate = $dailyAvgFromProduct > 0 ? $dailyAvgFromProduct : $dailyAvgFromPeriod;

                $item->inventory_days = $dailyRunRate > 0
                    ? (float) $item->current_stock / $dailyRunRate
                    : ($item->current_stock > 0 ? 9999 : 0);

                // Coeficiente de Variación (CV)
                $item->cv = $item->avg_daily_sales > 0 
                    ? (float) ($item->std_dev_sales / $item->avg_daily_sales) 
                    : 999; 

                // Lógica de Vencimiento FEFO (evaluación lote por lote contra el ritmo de venta)
                $item->is_expiring_soon = false;
                $item->has_expiration_risk = false;
                $item->months_to_expiration = null;
                $item->days_to_expiration = null;
                $item->risk_lot_date = null;
                $item->risk_expiring_units = 0;
                $item->risk_expiring_capital = 0;

                $productLots = $lotsByProduct->get($item->id);
                if ($productLots && $productLots->isNotEmpty() && $item->current_stock > 0) {
                    $today = now()->startOfDay();
                    $firstLot = $productLots->first();
                    $expDateFirst = Carbon::parse($firstLot->expiration_date)->startOfDay();
                    $daysDiffFirst = (int) $today->diffInDays($expDateFirst, false);
                    $item->next_expiration_date = $firstLot->expiration_date;
                    $item->days_to_expiration = $daysDiffFirst;
                    $item->months_to_expiration = round($daysDiffFirst / 30.4375, 1);

                    $currentDay = 0;
                    $totalRiskUnits = 0;
                    $firstRiskDate = null;

                    foreach ($productLots as $lot) {
                        $lotQty = (float) $lot->quantity;
                        $lotExpDate = Carbon::parse($lot->expiration_date)->startOfDay();
                        $daysToLotExp = (int) $today->diffInDays($lotExpDate, false);

                        if ($daysToLotExp <= 0) {
                            // Lote ya vencido en inventario
                            $totalRiskUnits += $lotQty;
                            if (!$firstRiskDate) {
                                $firstRiskDate = $lot->expiration_date;
                            }
                            $item->is_expiring_soon = true;
                            continue;
                        }

                        if ($dailyRunRate > 0) {
                            // Días cronológicos disponibles hasta el vencimiento de este lote específico
                            $availableDays = max(0, $daysToLotExp - $currentDay);
                            $maxSalesCapacity = $availableDays * $dailyRunRate;

                            if ($lotQty <= $maxSalesCapacity) {
                                // El lote es completamente absorbido por el ritmo de ventas antes de expirar
                                $daysUsed = $lotQty / $dailyRunRate;
                                $currentDay += $daysUsed;
                            } else {
                                // El ritmo de ventas no logra absorber todo el lote: el remanente vence en su fecha
                                $unitsSold = $maxSalesCapacity;
                                $unitsExpired = $lotQty - $unitsSold;
                                $totalRiskUnits += $unitsExpired;
                                $currentDay = $daysToLotExp;

                                if (!$firstRiskDate) {
                                    $firstRiskDate = $lot->expiration_date;
                                }
                                if ($daysToLotExp <= 180) {
                                    $item->is_expiring_soon = true;
                                }
                            }
                        } else {
                            // Sin ventas registradas en el periodo
                            if ($daysToLotExp <= 180) {
                                $totalRiskUnits += $lotQty;
                                if (!$firstRiskDate) {
                                    $firstRiskDate = $lot->expiration_date;
                                }
                                $item->is_expiring_soon = true;
                            }
                        }
                    }

                    if ($totalRiskUnits > 0) {
                        $item->has_expiration_risk = true;
                        $item->risk_lot_date = $firstRiskDate;
                        $item->risk_expiring_units = min($item->current_stock, round($totalRiskUnits, 2));
                        $item->risk_expiring_capital = round($item->risk_expiring_units * $item->last_cost, 2);
                    }
                }

                // Oferta individual
                $item->individual_offer_discount = !empty($item->individual_offer_discount) 
                    ? (float) $item->individual_offer_discount 
                    : null;
                $item->has_individual_offer = $item->individual_offer_discount !== null && $item->individual_offer_discount > 0;

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
            } elseif ($analysisType === 'frozen_capital') {
                // Capital Congelado / Parado:
                // 1) Stock atrapado sin ventas en el periodo (sold_units <= 0 y current_stock > 0)
                // 2) Clase C con rotación Z (CZ) con stock > 0
                // 3) Sobrestock severo (>= 180 días de inventario) en productos de baja rotación / bajo volumen (Clase C o Rotación Z)
                // 4) Riesgo real de vencimiento FEFO (unidades acumuladas del lote no se agotan antes de caducar)
                $data = $data->filter(function ($item) {
                    if ((float) $item->current_stock <= 0) {
                        return false;
                    }

                    $isZeroSales = $item->sold_units <= 0;
                    $isCZ = ($item->class_sales === 'C' && $item->class_rotation === 'Z');
                    $isLowRotationExcess = ($item->class_sales === 'C' || $item->class_rotation === 'Z') && (float) $item->inventory_days >= 180;
                    $isExpiringRisk = (bool) ($item->has_expiration_risk ?? false);

                    return $isZeroSales || $isCZ || $isLowRotationExcess || $isExpiringRisk;
                });
            } elseif ($analysisType === 'star_products') {
                // Productos Estrella: Ventas A y Margen A
                $data = $data->filter(function ($item) {
                    return $item->class_sales === 'A' && $item->class_margin === 'A';
                });
            } elseif ($analysisType === 'critical_stock') {
                // Quiebre Crítico y Riesgo de Quiebre: Productos Clase A o B con stock <= 0 o cobertura < 10 días
                $data = $data->filter(function ($item) {
                    $isClassAB = in_array($item->class_sales, ['A', 'B']);
                    $isStockout = $item->current_stock <= 0;
                    $isRisk = $item->inventory_days > 0 && $item->inventory_days < 10;
                    return $isClassAB && ($isStockout || $isRisk);
                });
            } elseif ($analysisType === 'negative_margin') {
                // Margen Negativo / Pérdida: Productos con margen < 0% y existencias actuales (stock > 0)
                $data = $data->filter(function ($item) {
                    return ((float)$item->margin_percentage < 0 || (float)$item->margin_amount < 0)
                        && (float)$item->current_stock > 0;
                })->sortBy('margin_percentage')->values();
            } elseif ($analysisType === 'expiring_risk') {
                // Capital Propenso a Vencerse por Expiración (Riesgo FEFO / Lotes con unidades no absorbibles <= 180 días)
                $data = $data->filter(function ($item) {
                    if ((float) $item->current_stock <= 0) {
                        return false;
                    }
                    $hasExpRisk = (bool) ($item->has_expiration_risk ?? false);
                    $riskUnits = (float) ($item->risk_expiring_units ?? 0);

                    return $hasExpRisk && $riskUnits > 0;
                })->sortBy(function ($item) {
                    return $item->days_to_expiration ?? 9999;
                })->values();
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
        });
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
