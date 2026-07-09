<?php

declare(strict_types=1);

namespace App\Repositories\Bi;

use App\Contracts\CustomerAnalytics;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerAnalyticsRepository implements CustomerAnalytics
{
    public function getKpis(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // Clientes Totales que han comprado en el rango de fechas
        $totalCustomers = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->distinct('client_id')
            ->count('client_id');

        // Clientes con más de una compra (Recompra) en el rango de fechas
        $repurchaseCount = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->select('client_id')
            ->groupBy('client_id')
            ->having(DB::raw('count(*)'), '>', 1)
            ->get()
            ->count();

        // LTV Promedio en el rango de fechas
        $totalRevenue = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->sum('total_amount_usd');
        
        $avgLtv = $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0;

        return [
            'total_customers' => $totalCustomers,
            'repurchase_count' => $repurchaseCount,
            'repurchase_rate' => $totalCustomers > 0 ? ($repurchaseCount / $totalCustomers) * 100 : 0,
            'avg_ltv' => round($avgLtv, 2),
        ];
    }

    public function getGrowthData(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->subMonth()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // Clientes nuevos por día en el rango
        $newCustomersDaily = DB::table('clients')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'new_customers_daily' => $newCustomersDaily,
        ];
    }

    public function getFrequencyData(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->subYear()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $distribution = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->select('client_id', DB::raw('count(*) as order_count'))
            ->groupBy('client_id')
            ->get()
            ->groupBy('order_count')
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();

        return $distribution;
    }

    public function getValueSegmentation(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // Gasto total por cliente en el periodo seleccionado
        $customers = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->select('client_id', DB::raw('SUM(total_amount_usd) as total_spent'))
            ->groupBy('client_id')
            ->orderByDesc('total_spent')
            ->get();

        $totalCount = $customers->count();
        if ($totalCount === 0) return [];

        $totalRevenue = $customers->sum('total_spent');

        $platinumCount = max(1, (int) ($totalCount * 0.05));
        $goldCount = max(1, (int) ($totalCount * 0.15));
        $silverCount = max(1, (int) ($totalCount * 0.30));

        $platinum = $customers->take($platinumCount);
        $gold = $customers->slice($platinumCount, $goldCount);
        $silver = $customers->slice($platinumCount + $goldCount, $silverCount);
        $bronze = $customers->slice($platinumCount + $goldCount + $silverCount);

        return [
            'platinum' => [
                'count' => $platinum->count(),
                'revenue' => $platinum->sum('total_spent'),
                'avg_per_client' => $platinum->avg('total_spent')
            ],
            'gold' => [
                'count' => $gold->count(),
                'revenue' => $gold->sum('total_spent'),
                'avg_per_client' => $gold->avg('total_spent')
            ],
            'silver' => [
                'count' => $silver->count(),
                'revenue' => $silver->sum('total_spent'),
                'avg_per_client' => $silver->avg('total_spent')
            ],
            'bronze' => [
                'count' => $bronze->count(),
                'revenue' => $bronze->sum('total_spent'),
                'avg_per_client' => $bronze->avg('total_spent')
            ],
            'total_revenue' => $totalRevenue
        ];
    }

    public function getCohortData(array $filters): array
    {
        // 1. Obtener la fecha de primera compra para cada cliente
        $firstPurchases = DB::table('orders')
            ->where('status', 'Completed')
            ->select('client_id', DB::raw('MIN(order_date) as first_purchase'))
            ->groupBy('client_id');

        // 2. Unir con todas sus compras para ver la retención por mes - SQLite friendly
        if (DB::connection()->getDriverName() === 'sqlite') {
            $cohorts = DB::table('orders')
                ->joinSub($firstPurchases, 'first_orders', function ($join) {
                    $join->on('orders.client_id', '=', 'first_orders.client_id');
                })
                ->where('orders.status', 'Completed')
                ->select(
                    DB::raw("strftime('%Y-%m', first_orders.first_purchase) as cohort_month"),
                    DB::raw("( (strftime('%Y', orders.order_date) - strftime('%Y', first_orders.first_purchase)) * 12 + (strftime('%m', orders.order_date) - strftime('%m', first_orders.first_purchase)) ) as month_number"),
                    DB::raw("COUNT(DISTINCT orders.client_id) as active_clients")
                )
                ->groupBy('cohort_month', 'month_number')
                ->orderBy('cohort_month')
                ->orderBy('month_number')
                ->get();
        } else {
            $cohorts = DB::table('orders')
                ->joinSub($firstPurchases, 'first_orders', function ($join) {
                    $join->on('orders.client_id', '=', 'first_orders.client_id');
                })
                ->where('orders.status', 'Completed')
                ->select(
                    DB::raw("DATE_FORMAT(first_orders.first_purchase, '%Y-%m') as cohort_month"),
                    DB::raw("PERIOD_DIFF(DATE_FORMAT(orders.order_date, '%Y%m'), DATE_FORMAT(first_orders.first_purchase, '%Y%m')) as month_number"),
                    DB::raw("COUNT(DISTINCT orders.client_id) as active_clients")
                )
                ->groupBy('cohort_month', 'month_number')
                ->orderBy('cohort_month')
                ->orderBy('month_number')
                ->get();
        }

        return $cohorts->toArray();
    }

    public function getRfmData(array $filters): array
    {
        $recencyExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(julianday('now') - julianday(MAX(orders.order_date)) AS INTEGER) as recency_days"
            : "DATEDIFF(NOW(), MAX(orders.order_date)) as recency_days";

        return DB::table('orders')
            ->where('orders.status', 'Completed')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->select(
                'clients.id',
                'clients.name',
                'clients.last_name',
                'clients.phone',
                DB::raw('MAX(orders.order_date) as last_order_date'),
                DB::raw('COUNT(*) as frequency'),
                DB::raw('SUM(orders.total_amount_usd) as monetary'),
                DB::raw($recencyExpr)
            )
            ->groupBy('clients.id', 'clients.name', 'clients.last_name', 'clients.phone')
            ->get()
            ->toArray();
    }
}
