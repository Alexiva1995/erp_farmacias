<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Contracts\Repositories\InventorySnapshotRepositoryInterface;
use App\Models\InventorySnapshot;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventorySnapshotService
{
    public function __construct(
        protected InventorySnapshotRepositoryInterface $repository
    ) {
    }

    /**
     * Obtener lista paginada de snapshots históricos.
     */
    public function listSnapshots(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateSnapshots($filters);
    }

    /**
     * Obtener el detalle de un snapshot con sus ítems calculados.
     */
    public function getSnapshotDetails(int $snapshotId, array $filters): array
    {
        return $this->repository->getSnapshotWithItems($snapshotId, $filters);
    }

    /**
     * Eliminar un snapshot histórico.
     */
    public function deleteSnapshot(int $snapshotId): bool
    {
        return $this->repository->deleteSnapshot($snapshotId);
    }

    /**
     * Obtener todos los ítems para exportar a Excel.
     */
    public function getItemsForExport(int $snapshotId): Collection
    {
        return $this->repository->getSnapshotItemsForExport($snapshotId);
    }

    /**
     * Obtener el análisis de los 4 módulos de control estratégico para un snapshot:
     * - Módulo 1: Fotografía General (KPIs de Cierre)
     * - Módulo 2: Monitoreo de Capital Recuperado (Productos CZ)
     * - Módulo 3: Control de Compras Prioritarias (Efectividad de Reabastecimiento A/B)
     * - Módulo 4: Alerta de Márgenes Saludables
     */
    public function getSnapshotAuditModules(int $snapshotId): array
    {
        $snapshot = InventorySnapshot::with('creator:id,username')->findOrFail($snapshotId);
        $snapshotItems = $this->repository->getSnapshotItemsForExport($snapshotId);

        // Obtener datos actuales de los productos en vivo
        $currentProducts = DB::table('products')
            ->select('id', 'name', 'stock', 'unit_cost', 'sale_price')
            ->get()
            ->keyBy('id');

        // =========================================================================
        // MÓDULO 1: FOTOGRAFÍA GENERAL (KPIS DE CIERRE)
        // =========================================================================
        $totalInventoryValue = (float) $snapshot->total_inventory_value;
        
        // Capital congelado en CZ (Clase C o Z con stock > 0)
        $czItems = $snapshotItems->filter(fn($i) => in_array($i->sales_class, ['C', 'Z']) && (float)$i->current_stock_units > 0);
        $frozenCapitalCz = (float) $czItems->sum('inventory_value_usd');

        // Capital retenido en sobrestock A/B (Clase A o B con cobertura > 90d)
        $abOverstockItems = $snapshotItems->filter(fn($i) => in_array($i->sales_class, ['A', 'B']) && (bool)$i->is_overstock);
        $overstockCapitalAb = (float) $abOverstockItems->sum('inventory_value_usd');

        // SKUs en Quiebre de Stock (Stock = 0)
        $stockoutItems = $snapshotItems->filter(fn($i) => (float)$i->current_stock_units <= 0);
        $stockoutCount = $stockoutItems->count();
        $stockoutAbCount = $stockoutItems->filter(fn($i) => in_array($i->sales_class, ['A', 'B']))->count();

        $module1 = [
            'total_inventory_value' => $totalInventoryValue,
            'total_inventory_units' => (float) $snapshot->total_inventory_units,
            'total_sales_value' => (float) $snapshot->total_sales_value,
            'frozen_capital_cz' => $frozenCapitalCz,
            'overstock_capital_ab' => $overstockCapitalAb,
            'stockout_skus_count' => $stockoutCount,
            'stockout_ab_count' => $stockoutAbCount,
            'total_products_count' => (int) $snapshot->total_products,
        ];

        // =========================================================================
        // MÓDULO 2: MONITOREO DE CAPITAL RECUPERADO (PRODUCTOS CZ)
        // =========================================================================
        $czMonitoringList = [];
        $totalInitialCzCapital = 0.0;
        $totalCurrentCzCapital = 0.0;
        $totalUnitsReleasedCz = 0.0;

        foreach ($czItems as $item) {
            $currProd = $currentProducts->get($item->product_id);
            $currStock = $currProd ? max(0, (float)$currProd->stock) : 0.0;
            $currCost = $currProd ? (float)$currProd->unit_cost : (float)$item->unit_cost_usd;
            $currValue = $currStock * $currCost;

            $initialStock = (float) $item->current_stock_units;
            $initialValue = (float) $item->inventory_value_usd;

            $totalInitialCzCapital += $initialValue;
            $totalCurrentCzCapital += $currValue;

            $cashReleased = $initialValue - $currValue; // Dinero liberado a caja ($)
            $unitsReleased = max(0, $initialStock - $currStock);
            $totalUnitsReleasedCz += $unitsReleased;

            $status = 'Sin Movimiento';
            if ($currStock <= 0) {
                $status = 'Liberado Total (Agotado)';
            } elseif ($currStock < $initialStock) {
                $status = 'Liberado Parcial';
            } elseif ($currStock > $initialStock) {
                $status = 'Incrementó Stock';
            }

            $czMonitoringList[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'laboratory_name' => $item->laboratory_name ?? 'N/A',
                'sales_class' => $item->sales_class,
                'snapshot_stock' => round($initialStock, 1),
                'snapshot_value_usd' => round($initialValue, 2),
                'current_stock' => round($currStock, 1),
                'current_value_usd' => round($currValue, 2),
                'units_released' => round($unitsReleased, 1),
                'cash_released_usd' => round($cashReleased, 2),
                'status' => $status,
            ];
        }

        // Ordenar los que más dinero liberaron primero
        usort($czMonitoringList, fn($a, $b) => $b['cash_released_usd'] <=> $a['cash_released_usd']);

        $totalCashReleased = max(0, $totalInitialCzCapital - $totalCurrentCzCapital);
        $recoveryPercentage = $totalInitialCzCapital > 0 ? ($totalCashReleased / $totalInitialCzCapital) * 100 : 0.0;

        $module2 = [
            'total_initial_cz_capital' => round($totalInitialCzCapital, 2),
            'total_current_cz_capital' => round($totalCurrentCzCapital, 2),
            'total_cash_released' => round($totalCashReleased, 2),
            'total_units_released' => round($totalUnitsReleasedCz, 1),
            'recovery_percentage' => round($recoveryPercentage, 2),
            'items_count' => count($czMonitoringList),
            'items' => $czMonitoringList, // Lista completa sin recortes
        ];

        // =========================================================================
        // MÓDULO 3: CONTROL DE COMPRAS PRIORITARIAS (REABASTECIMIENTO A/B)
        // =========================================================================
        $criticalAbItems = $snapshotItems->filter(function ($i) {
            $isAb = in_array($i->sales_class, ['A', 'B']);
            $isStockout = (float) $i->current_stock_units <= 0;
            $isCriticalCoverage = (float) $i->coverage_days > 0 && (float) $i->coverage_days < 10;
            return $isAb && ($isStockout || $isCriticalCoverage);
        });

        $abRestockList = [];
        $restockedCount = 0;

        foreach ($criticalAbItems as $item) {
            $currProd = $currentProducts->get($item->product_id);
            $currStock = $currProd ? max(0, (float)$currProd->stock) : 0.0;
            $snapStock = (float) $item->current_stock_units;

            $status = 'Aún en Quiebre';
            $color = 'error';
            if ($currStock >= 10) {
                $status = 'Reabastecido con Éxito';
                $color = 'success';
                $restockedCount++;
            } elseif ($currStock > 0) {
                $status = 'Reabastecimiento Parcial';
                $color = 'warning';
                $restockedCount++;
            }

            $abRestockList[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'laboratory_name' => $item->laboratory_name ?? 'N/A',
                'sales_class' => $item->sales_class,
                'snapshot_stock' => round($snapStock, 1),
                'snapshot_coverage_days' => round((float)$item->coverage_days, 1),
                'current_stock' => round($currStock, 1),
                'restock_status' => $status,
                'status_color' => $color,
            ];
        }

        $totalCriticalAb = $criticalAbItems->count();
        $restockEffectiveness = $totalCriticalAb > 0 ? ($restockedCount / $totalCriticalAb) * 100 : 100.0;

        $module3 = [
            'total_critical_items' => $totalCriticalAb,
            'restocked_count' => $restockedCount,
            'still_stockout_count' => $totalCriticalAb - $restockedCount,
            'effectiveness_rate' => round($restockEffectiveness, 1),
            'items' => $abRestockList,
        ];

        // =========================================================================
        // MÓDULO 4: ALERTA DE MÁRGENES SALUDABLES
        // =========================================================================
        $marginAlerts = [];
        $negativeMarginCount = 0;
        $lowMarginCount = 0;
        $healthyMarginCount = 0;

        foreach ($snapshotItems as $item) {
            $marginPct = (float) $item->margin_percentage;
            $stock = (float) $item->current_stock_units;

            if ($marginPct < 0 && $stock > 0) {
                $negativeMarginCount++;
                $marginAlerts[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'laboratory_name' => $item->laboratory_name ?? 'N/A',
                    'unit_cost_usd' => round((float)$item->unit_cost_usd, 4),
                    'sale_price_usd' => round((float)$item->sale_price_usd, 4),
                    'margin_percentage' => round($marginPct, 2),
                    'current_stock' => round($stock, 1),
                    'inventory_value_usd' => round((float)$item->inventory_value_usd, 2),
                    'risk_level' => 'Pérdida / Margen Negativo (<0%)',
                    'severity' => 'error',
                ];
            } elseif ($marginPct >= 0 && $marginPct < 15 && $stock > 0) {
                $lowMarginCount++;
                $marginAlerts[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'laboratory_name' => $item->laboratory_name ?? 'N/A',
                    'unit_cost_usd' => round((float)$item->unit_cost_usd, 4),
                    'sale_price_usd' => round((float)$item->sale_price_usd, 4),
                    'margin_percentage' => round($marginPct, 2),
                    'current_stock' => round($stock, 1),
                    'inventory_value_usd' => round((float)$item->inventory_value_usd, 2),
                    'risk_level' => 'Margen Bajo (<15%)',
                    'severity' => 'warning',
                ];
            } else {
                $healthyMarginCount++;
            }
        }

        // Ordenar los más graves (menor margen) primero
        usort($marginAlerts, fn($a, $b) => $a['margin_percentage'] <=> $b['margin_percentage']);

        $module4 = [
            'negative_margin_count' => $negativeMarginCount,
            'low_margin_count' => $lowMarginCount,
            'healthy_margin_count' => $healthyMarginCount,
            'total_evaluated' => $snapshotItems->count(),
            'alerts' => $marginAlerts,
        ];

        return [
            'snapshot' => $snapshot,
            'module_1_summary' => $module1,
            'module_2_cz_recovery' => $module2,
            'module_3_ab_restock' => $module3,
            'module_4_margin_alerts' => $module4,
        ];
    }

    /**
     * Generar y persistir una nueva Foto Finish de Inventario a una fecha de corte determinada.
     *
     * @param string $cutoffDateStr Fecha de corte (YYYY-MM-DD)
     * @param int $periodDays Días a considerar para las ventas (default 30)
     * @param string|null $name Nombre identificador de la foto finish
     * @param int|null $userId ID del usuario que solicita la foto
     * @param bool $isAutomatic Si fue generada por scheduler
     * @return InventorySnapshot
     */
    public function generateSnapshot(
        string $cutoffDateStr,
        int $periodDays = 30,
        ?string $name = null,
        ?int $userId = null,
        bool $isAutomatic = false
    ): InventorySnapshot {
        $cutoffDate = Carbon::parse($cutoffDateStr)->endOfDay();
        $startDate = $cutoffDate->copy()->subDays($periodDays)->startOfDay();

        $snapshotName = $name ?: ('Foto Finish ' . $cutoffDate->format('Y-m-d') . ($isAutomatic ? ' (Automática)' : ''));

        // 1. Obtener ventas agrupadas por producto en el periodo [startDate, cutoffDate]
        $salesData = DB::table('order_details')
            ->select(
                'order_details.product_id',
                DB::raw('SUM(order_details.quantity) as sold_units'),
                DB::raw('SUM(order_details.quantity * CASE WHEN order_details.unit_price_usd > 0 THEN order_details.unit_price_usd WHEN orders.currency = \'USD\' THEN order_details.price ELSE (order_details.price / NULLIF(orders.usd_conversion, 0)) END) as total_sales_usd'),
                DB::raw('SUM(order_details.quantity * order_details.unit_cost) as total_cost_usd')
            )
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.order_date', [$startDate->toDateTimeString(), $cutoffDate->toDateTimeString()])
            ->whereNotNull('order_details.product_id')
            ->groupBy('order_details.product_id')
            ->get()
            ->keyBy('product_id');

        // 2. Obtener todos los lotes activos agrupados por producto para evaluación cronológica FEFO
        $lotsByProduct = DB::table('product_lots')
            ->select('product_id', 'quantity', 'expiration_date')
            ->where('quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->orderBy('expiration_date', 'asc')
            ->get()
            ->groupBy('product_id');

        // 3. Obtener el catálogo de productos con sus costos, precios y laboratorios
        $products = DB::table('products')
            ->select(
                'products.id',
                'products.name as product_name',
                'laboratories.name as laboratory_name',
                'products.stock as current_stock',
                'products.unit_cost as unit_cost_usd',
                'products.sale_price as sale_price_usd'
            )
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->get();

        $itemsToProcess = [];
        $totalSumSalesUsd = 0.0;

        foreach ($products as $prod) {
            $prodId = (int) $prod->id;
            $sales = $salesData->get($prodId);
            $productLots = $lotsByProduct->get($prodId);

            $currentStock = max(0, (float) ($prod->current_stock ?? 0));
            $soldUnits = $sales ? max(0, (float) $sales->sold_units) : 0.0;
            $totalSalesUsd = $sales ? max(0, (float) $sales->total_sales_usd) : 0.0;
            $totalCostSalesUsd = $sales ? max(0, (float) $sales->total_cost_usd) : 0.0;

            // Filtro de inclusión: stock > 0 o ventas > 0 en el periodo
            if ($currentStock <= 0 && $soldUnits <= 0) {
                continue;
            }

            $unitCost = (float) ($prod->unit_cost_usd ?? 0);
            $salePrice = (float) ($prod->sale_price_usd ?? 0);
            $inventoryValue = $currentStock * $unitCost;

            // 1. Regla de Cobertura en Días: stock / (ventas_30d / 30). Si ventas_30d es 0 => 999
            $dailySales = $soldUnits / max(1, $periodDays);
            if ($dailySales > 0) {
                $coverageDays = round($currentStock / $dailySales, 2);
            } else {
                $coverageDays = $currentStock > 0 ? 999.0 : 0.0;
            }

            // 2. Regla de Sobrestock: TRUE si (cobertura_dias > 90 Y stock_actual > 0)
            $isOverstock = ($coverageDays > 90 && $currentStock > 0);

            // Margen porcentual
            if ($totalSalesUsd > 0) {
                $marginAmount = $totalSalesUsd - $totalCostSalesUsd;
                $marginPercentage = ($marginAmount / $totalSalesUsd) * 100;
            } elseif ($salePrice > 0) {
                $marginAmount = 0.0;
                $marginPercentage = (($salePrice - $unitCost) / $salePrice) * 100;
            } else {
                $marginAmount = 0.0;
                $marginPercentage = 0.0;
            }

            // GMROI Anualizado (%)
            $gmroiAnnual = $inventoryValue > 0
                ? ($marginAmount / $inventoryValue) * (365 / max(1, $periodDays)) * 100
                : ($marginAmount > 0 ? 9999.0 : 0.0);

            // 3. Simulación cronológica multi-lote FEFO (Mes a Mes) con deducción de mermas
            $daysToExpiration = null;
            $riskExpiringUnits = 0.0;

            if ($productLots && $productLots->isNotEmpty() && $currentStock > 0) {
                $firstLot = $productLots->first();
                $lotExpDateFirst = Carbon::parse($firstLot->expiration_date)->startOfDay();
                $daysToExpiration = (int) $cutoffDate->copy()->startOfDay()->diffInDays($lotExpDateFirst, false);

                $currentDay = 0;
                foreach ($productLots as $lot) {
                    $lotQty = (float) $lot->quantity;
                    $lotExpDate = Carbon::parse($lot->expiration_date)->startOfDay();
                    $daysToLotExp = (int) $cutoffDate->copy()->startOfDay()->diffInDays($lotExpDate, false);

                    if ($daysToLotExp <= 0) {
                        $riskExpiringUnits += $lotQty;
                        continue;
                    }

                    if ($dailySales > 0) {
                        $availableDays = max(0, $daysToLotExp - $currentDay);
                        $maxSalesCapacity = $availableDays * $dailySales;

                        if ($lotQty <= $maxSalesCapacity) {
                            $daysUsed = $lotQty / $dailySales;
                            $currentDay += $daysUsed;
                        } else {
                            $unitsSold = $maxSalesCapacity;
                            $unitsExpired = $lotQty - $unitsSold;
                            $riskExpiringUnits += $unitsExpired;
                            $currentDay = $daysToLotExp;
                        }
                    } else {
                        if ($daysToLotExp <= 180) {
                            $riskExpiringUnits += $lotQty;
                        }
                    }
                }
            }

            $riskExpiringValue = round($riskExpiringUnits * $unitCost, 2);
            $hasExpRisk = ($riskExpiringUnits > 0);

            $itemsToProcess[] = [
                'product_id' => $prodId,
                'product_name' => $prod->product_name ?? ('Producto #' . $prodId),
                'laboratory_name' => $prod->laboratory_name,
                'sold_units_30d' => round($soldUnits, 2),
                'total_sales_usd_30d' => round($totalSalesUsd, 2),
                'current_stock_units' => round($currentStock, 2),
                'unit_cost_usd' => round($unitCost, 4),
                'sale_price_usd' => round($salePrice, 4),
                'inventory_value_usd' => round($inventoryValue, 2),
                'margin_percentage' => round($marginPercentage, 2),
                'coverage_days' => round($coverageDays, 2),
                'gmroi_annual_percentage' => round($gmroiAnnual, 2),
                'days_to_expiration' => $daysToExpiration,
                'risk_expiring_units' => round($riskExpiringUnits, 2),
                'risk_expiring_value_usd' => $riskExpiringValue,
                'has_expiration_risk' => $hasExpRisk,
                'is_overstock' => $isOverstock,
            ];

            $totalSumSalesUsd += $totalSalesUsd;
        }

        // 4. Asignar clasificación de ventas Pareto (A, B, C, Z)
        // Ordenar descendentemente por total_sales_usd_30d
        usort($itemsToProcess, fn($a, $b) => $b['total_sales_usd_30d'] <=> $a['total_sales_usd_30d']);

        $runningSales = 0.0;
        foreach ($itemsToProcess as &$item) {
            if ($item['total_sales_usd_30d'] <= 0 || $totalSumSalesUsd <= 0) {
                $item['sales_class'] = 'Z';
                continue;
            }

            $runningSales += $item['total_sales_usd_30d'];
            $accumulatedPct = ($runningSales / $totalSumSalesUsd) * 100;

            if ($accumulatedPct <= 80) {
                $item['sales_class'] = 'A';
            } elseif ($accumulatedPct <= 95) {
                $item['sales_class'] = 'B';
            } else {
                $item['sales_class'] = 'C';
            }
        }
        unset($item);

        // 5. Calcular totales para la cabecera
        $totalProducts = count($itemsToProcess);
        $totalInventoryUnits = array_sum(array_column($itemsToProcess, 'current_stock_units'));
        $totalInventoryValue = array_sum(array_column($itemsToProcess, 'inventory_value_usd'));
        $totalSalesUnits = array_sum(array_column($itemsToProcess, 'sold_units_30d'));
        $totalSalesValue = array_sum(array_column($itemsToProcess, 'total_sales_usd_30d'));

        $overstockItems = array_filter($itemsToProcess, fn($i) => $i['is_overstock']);
        $overstockCount = count($overstockItems);
        $overstockInventoryValue = array_sum(array_column($overstockItems, 'inventory_value_usd'));

        $expRiskItems = array_filter($itemsToProcess, fn($i) => !empty($i['has_expiration_risk']));
        $expRiskCount = count($expRiskItems);
        $expRiskInventoryValue = array_sum(array_column($expRiskItems, 'risk_expiring_value_usd'));

        $headerData = [
            'name' => $snapshotName,
            'cutoff_date' => $cutoffDate->toDateString(),
            'period_days' => $periodDays,
            'total_products' => $totalProducts,
            'total_inventory_units' => round($totalInventoryUnits, 2),
            'total_inventory_value' => round($totalInventoryValue, 2),
            'total_sales_units' => round($totalSalesUnits, 2),
            'total_sales_value' => round($totalSalesValue, 2),
            'overstock_products_count' => $overstockCount,
            'overstock_inventory_value' => round($overstockInventoryValue, 2),
            'expiring_risk_products_count' => $expRiskCount,
            'expiring_risk_inventory_value' => round($expRiskInventoryValue, 2),
            'is_automatic' => $isAutomatic,
            'created_by_user_id' => $userId,
        ];

        return $this->repository->createSnapshot($headerData, $itemsToProcess);
    }
}
