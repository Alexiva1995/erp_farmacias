<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Contracts\Repositories\SkuReportRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SkuReportService
{
    protected $skuReportRepository;

    public function __construct(SkuReportRepositoryInterface $skuReportRepository)
    {
        $this->skuReportRepository = $skuReportRepository;
    }

    /**
     * Genera el reporte de Margen Real calculando las capas en SQL.
     */
    public function generateReport(array $filters, $perPage = 15)
    {
        $baseQuery = $this->skuReportRepository->getBaseQuery($filters);

        $minDate = '2026-04-01 00:00:00';
        $startDate = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : $minDate;
        if ($startDate < $minDate) {
            $startDate = $minDate;
        }

        $expiredQuery = DB::table('expired_logs')
            ->select('product_id', DB::raw('SUM(total_lost_value) as total_expired_cost'))
            ->where('created_at', '>=', $startDate);
        if (!empty($filters['end_date'])) {
            $expiredQuery->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }
        $expiredQuery->groupBy('product_id');

        $wrappedQuery = DB::table(DB::raw("({$baseQuery->toSql()}) as sub"))
            ->select([
                'sub.*',
                DB::raw('COALESCE(expired.total_expired_cost, 0) as loss_value'),
                DB::raw('(sub.total_revenue - sub.total_historical_cost) as net_margin_value'),
                DB::raw('(sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) as real_margin_value'),
                DB::raw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost) / sub.total_revenue) * 100 ELSE 0 END as net_margin_percent'),
                DB::raw('CASE WHEN sub.total_revenue > 0 THEN ((sub.total_revenue - sub.total_historical_cost - COALESCE(expired.total_expired_cost, 0)) / sub.total_revenue) * 100 ELSE 0 END as real_margin_percent'),
            ])
            ->leftJoinSub($expiredQuery, 'expired', 'sub.product_id', '=', 'expired.product_id');

        // Extraer los bindings originales de cada query
        $baseBindings = $baseQuery->getBindings();
        $expiredBindings = $expiredQuery->getBindings();

        // Limpiar todas las claves de bindings para evitar que Laravel arrastre duplicados en 'where' o 'union'
        $ref = new \ReflectionClass($wrappedQuery);
        $prop = $ref->getProperty('bindings');
        $prop->setAccessible(true);
        
        $rawBindings = [
            'select' => [],
            'from' => [],
            'join' => array_merge($baseBindings, $expiredBindings),
            'where' => [],
            'groupBy' => [],
            'having' => [],
            'order' => [],
            'union' => [],
            'unionOrder' => [],
        ];
        
        $prop->setValue($wrappedQuery, $rawBindings);

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
                }
            });
        }

        if (!empty($filters['sortBy']) && !empty($filters['orderBy'])) {
            $allowedSorts = ['total_sold', 'product_name', 'real_margin_percent', 'gross_margin_percent', 'net_margin_percent'];
            if (in_array($filters['sortBy'], $allowedSorts)) {
                $wrappedQuery->orderBy($filters['sortBy'], $filters['orderBy']);
            }
        } else {
            $wrappedQuery->orderBy('total_revenue', 'desc');
        }

        // Obtener el conteo total envolviendo el SQL para evitar que Laravel genere count(*) incorrecto
        $totalCountResult = DB::select("select count(*) as cnt from (" . $wrappedQuery->toSql() . ") as tmp", $wrappedQuery->getBindings());
        $total = $totalCountResult[0]->cnt ?? 0;

        // Paginación manual para evitar que Laravel intente compilar count(*) directamente
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $offset = ($page - 1) * $perPage;
        $paginatedSql = $wrappedQuery->toSql() . " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $itemsData = DB::select($paginatedSql, $wrappedQuery->getBindings());

        $items = collect($itemsData)->map(function ($item) {
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

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );

        return $paginated;
    }

    /**
     * Calcula los resúmenes financieros globales de toda la consulta (sin paginar) de manera optimizada.
     */
    public function getGlobalSummary(array $filters): array
    {
        $baseQuery = $this->skuReportRepository->getBaseQuery($filters);
        
        $totals = DB::table(DB::raw("({$baseQuery->toSql()}) as sub"))
            ->mergeBindings($baseQuery->getQuery())
            ->select([
                DB::raw('SUM(total_revenue) as total_revenue'),
                DB::raw('SUM(total_historical_cost) as total_historical_cost'),
                DB::raw('SUM(total_discount_amount) as total_discounts'),
            ])
            ->first();

        $totalRevenue = $totals ? (float) $totals->total_revenue : 0.0;
        $totalHistoricalCost = $totals ? (float) $totals->total_historical_cost : 0.0;
        $totalDiscountAmount = $totals ? (float) $totals->total_discounts : 0.0;

        $minDate = '2026-04-01 00:00:00';
        $startDate = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : $minDate;
        if ($startDate < $minDate) {
            $startDate = $minDate;
        }

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

        // Conteo optimizado directamente desde el listado paginado base de datos
        $criticalSkus = DB::table(DB::raw("({$baseQuery->toSql()}) as sub"))
            ->mergeBindings($baseQuery->getQuery())
            ->whereRaw('(total_revenue - total_historical_cost) < 0 OR total_revenue <= 0')
            ->count();

        return [
            'total_revenue' => $totalRevenue,
            'total_loss' => $totalLosses,
            'total_discounts' => $totalDiscountAmount,
            'critical_skus' => $criticalSkus,
            'global_margin_net' => $globalMarginNet,
            'global_margin_real' => $globalMarginReal
        ];
    }
}
