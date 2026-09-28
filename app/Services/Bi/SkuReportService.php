<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Contracts\Repositories\SkuReportRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class SkuReportService
{
    protected SkuReportRepositoryInterface $skuReportRepository;

    public function __construct(SkuReportRepositoryInterface $skuReportRepository)
    {
        $this->skuReportRepository = $skuReportRepository;
    }

    /**
     * Genera el reporte de Margen Real calculando las capas en SQL de forma optimizada.
     */
    public function generateReport(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $baseQuery = $this->skuReportRepository->getBaseQuery($filters);

        $startDate = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : now()->startOfMonth()->format('Y-m-d 00:00:00');

        $expiredQuery = DB::table('expired_logs')
            ->select('product_id', DB::raw('SUM(total_lost_value) as total_expired_cost'))
            ->where('created_at', '>=', $startDate);

        if (!empty($filters['end_date'])) {
            $expiredQuery->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }
        $expiredQuery->groupBy('product_id');

        // Construcción limpia del query envolvente usando fromSub y leftJoinSub
        $wrappedQuery = DB::query()
            ->fromSub($baseQuery, 'sub')
            ->select([
                'sub.*',
                DB::raw('COALESCE(expired.total_expired_cost, 0) as loss_value'),
                DB::raw('(sub.total_revenue - sub.total_historical_cost) as net_margin_value'),
                DB::raw('(sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) as real_margin_value'),
                DB::raw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost) / sub.total_revenue) * 100 ELSE 0 END as net_margin_percent'),
                DB::raw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) / sub.total_revenue) * 100 ELSE 0 END as real_margin_percent'),
            ])
            ->leftJoinSub($expiredQuery, 'expired', 'sub.product_id', '=', 'expired.product_id');

        if (!empty($filters['semaphore'])) {
            $wrappedQuery->where(function ($q) use ($filters) {
                if ($filters['semaphore'] === 'verde') {
                    $q->whereRaw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) / sub.total_revenue) * 100 ELSE 0 END > 25');
                } elseif ($filters['semaphore'] === 'amarillo') {
                    $q->whereRaw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) / sub.total_revenue) * 100 ELSE 0 END BETWEEN 10 AND 25');
                } elseif ($filters['semaphore'] === 'rojo') {
                    $q->whereRaw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) / sub.total_revenue) * 100 ELSE 0 END BETWEEN 0 AND 9.9999');
                } elseif ($filters['semaphore'] === 'negro') {
                    $q->whereRaw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) / sub.total_revenue) * 100 ELSE 0 END < 0')
                      ->orWhere('sub.total_revenue', '<=', 0);
                } elseif ($filters['semaphore'] === 'critico') {
                    $q->whereRaw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) / sub.total_revenue) * 100 ELSE 0 END < 10')
                      ->orWhere('sub.total_revenue', '<=', 0);
                }
            });
        }

        if (!empty($filters['has_loss'])) {
            $wrappedQuery->whereRaw('COALESCE(expired.total_expired_cost, 0) > 0');
        }

        if (!empty($filters['has_discount'])) {
            $wrappedQuery->where('sub.total_discount_amount', '>', 0);
        }

        if (!empty($filters['sortBy']) && !empty($filters['orderBy'])) {
            $allowedSorts = ['total_sold', 'product_name', 'real_margin_percent', 'gross_margin_percent', 'net_margin_percent', 'current_stock'];
            if (in_array($filters['sortBy'], $allowedSorts, true)) {
                $wrappedQuery->orderBy($filters['sortBy'], $filters['orderBy']);
            }
        } else {
            $wrappedQuery->orderBy('total_revenue', 'desc');
        }

        // Obtener el conteo total con subquery directo seguro
        $total = $wrappedQuery->count();

        // Paginación
        $page = Paginator::resolveCurrentPage() ?: 1;
        $offset = ($page - 1) * $perPage;

        $itemsData = (clone $wrappedQuery)->offset($offset)->limit($perPage)->get();

        $items = $itemsData->map(function ($item) {
            $realMarginPercent = (float) $item->real_margin_percent;
            $totalRevenue = (float) $item->total_revenue;

            if ($totalRevenue <= 0 || $realMarginPercent < 0) {
                $semaphoreColor = 'negro';
            } elseif ($realMarginPercent > 25) {
                $semaphoreColor = 'verde';
            } elseif ($realMarginPercent >= 10) {
                $semaphoreColor = 'amarillo';
            } else {
                $semaphoreColor = 'rojo';
            }

            $unitCost = (float) $item->current_cost;
            $listPrice = (float) $item->list_price;
            $totalSoldQty = (float) $item->total_sold;
            
            $grossMarginUnit = $listPrice - $unitCost;
            $item->gross_margin_value = $grossMarginUnit * $totalSoldQty;
            $item->gross_margin_percent = $listPrice > 0 ? ($grossMarginUnit / $listPrice) * 100 : 0;

            $discountAvgAmount = $item->total_discount_amount > 0 && $totalSoldQty > 0 
                ? ($item->total_discount_amount / $totalSoldQty) : 0;
            $item->discount_avg_percent = $listPrice > 0 ? ($discountAvgAmount / $listPrice) * 100 : 0;

            $item->semaphore = $semaphoreColor;

            return $item;
        });

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    /**
     * Calcula los resúmenes financieros globales y métricas BI extendidas.
     */
    public function getGlobalSummary(array $filters): array
    {
        $baseQuery = $this->skuReportRepository->getBaseQuery($filters);
        
        $totals = DB::query()
            ->fromSub($baseQuery, 'sub')
            ->select([
                DB::raw('SUM(total_revenue) as total_revenue'),
                DB::raw('SUM(total_historical_cost) as total_historical_cost'),
                DB::raw('SUM(total_discount_amount) as total_discounts'),
                DB::raw('SUM(total_sold * list_price) as total_gross_sales'),
                DB::raw('SUM(current_stock * current_cost) as total_inventory_valuation'),
            ])
            ->first();

        $totalRevenue = $totals ? (float) $totals->total_revenue : 0.0;
        $totalHistoricalCost = $totals ? (float) $totals->total_historical_cost : 0.0;
        $totalDiscountAmount = $totals ? (float) $totals->total_discounts : 0.0;
        $totalGrossSales = $totals ? (float) $totals->total_gross_sales : 0.0;
        $totalInventoryValuation = $totals ? (float) $totals->total_inventory_valuation : 0.0;

        $startDate = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : now()->startOfMonth()->format('Y-m-d 00:00:00');

        $totalLossesQuery = DB::table('expired_logs')
            ->where('created_at', '>=', $startDate);
        if (!empty($filters['end_date'])) {
            $totalLossesQuery->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }
        $totalLosses = (float) $totalLossesQuery->sum('total_lost_value');

        $netMarginTotal = $totalRevenue - $totalHistoricalCost;
        $globalMarginNet = $totalRevenue > 0 ? ($netMarginTotal / $totalRevenue) * 100 : 0;
        
        $realMarginTotal = $netMarginTotal - $totalLosses;
        $globalMarginReal = $totalRevenue > 0 ? ($realMarginTotal / $totalRevenue) * 100 : 0;

        // Distribución de SKUs por semáforo
        $wrappedForSemaphores = DB::query()
            ->fromSub($baseQuery, 'sub')
            ->select([
                'sub.product_id',
                'sub.total_revenue',
                DB::raw('(sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) as real_margin_value'),
                DB::raw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) / sub.total_revenue) * 100 ELSE 0 END as real_margin_percent')
            ])
            ->leftJoinSub(
                DB::table('expired_logs')
                    ->select('product_id', DB::raw('SUM(total_lost_value) as total_expired_cost'))
                    ->where('created_at', '>=', $startDate)
                    ->when(!empty($filters['end_date']), fn($q) => $q->where('created_at', '<=', $filters['end_date'] . ' 23:59:59'))
                    ->groupBy('product_id'),
                'expired',
                'sub.product_id',
                '=',
                'expired.product_id'
            );

        $semaphoreRows = $wrappedForSemaphores->get();

        $greenCount = 0;
        $yellowCount = 0;
        $redCount = 0;
        $blackCount = 0;

        foreach ($semaphoreRows as $row) {
            $margin = (float) $row->real_margin_percent;
            $rev = (float) $row->total_revenue;
            if ($rev <= 0 || $margin < 0) {
                $blackCount++;
            } elseif ($margin > 25) {
                $greenCount++;
            } elseif ($margin >= 10) {
                $yellowCount++;
            } else {
                $redCount++;
            }
        }

        $criticalSkus = $redCount + $blackCount;
        $gmroi = $totalInventoryValuation > 0 ? ($realMarginTotal / $totalInventoryValuation) : 0.0;

        return [
            'total_revenue' => $totalRevenue,
            'total_gross_sales' => $totalGrossSales,
            'total_loss' => $totalLosses,
            'total_discounts' => $totalDiscountAmount,
            'total_historical_cost' => $totalHistoricalCost,
            'total_inventory_valuation' => $totalInventoryValuation,
            'critical_skus' => $criticalSkus,
            'global_margin_net' => $globalMarginNet,
            'global_margin_real' => $globalMarginReal,
            'gmroi' => round($gmroi, 2),
            'semaphore_counts' => [
                'green' => $greenCount,
                'yellow' => $yellowCount,
                'red' => $redCount,
                'black' => $blackCount,
            ]
        ];
    }

    /**
     * Entrega datos optimizados para los gráficos de BI en Frontend.
     */
    public function getChartsData(array $filters): array
    {
        $summary = $this->getGlobalSummary($filters);

        // Cascada Financiera (Waterfall Data)
        $grossSales = $summary['total_gross_sales'] ?: $summary['total_revenue'];
        $waterfall = [
            ['name' => 'Venta Bruta (P. Lista)', 'value' => round($grossSales, 2), 'type' => 'base'],
            ['name' => 'Descuentos Promocionales', 'value' => -round($summary['total_discounts'], 2), 'type' => 'deduction'],
            ['name' => 'Ingreso Neto Cobrado', 'value' => round($summary['total_revenue'], 2), 'type' => 'subtotal'],
            ['name' => 'Costo Mercancía (COGS)', 'value' => -round($summary['total_historical_cost'], 2), 'type' => 'deduction'],
            ['name' => 'Mermas por Vencimiento', 'value' => -round($summary['total_loss'], 2), 'type' => 'deduction'],
            ['name' => 'Margen Real Efectivo', 'value' => round($summary['total_revenue'] - $summary['total_historical_cost'] - $summary['total_loss'], 2), 'type' => 'total'],
        ];

        return [
            'waterfall' => $waterfall,
            'semaphore_distribution' => $summary['semaphore_counts'],
            'summary' => $summary,
        ];
    }
}
