<?php

declare(strict_types=1);

namespace App\Repositories\Bi;

use App\Contracts\CustomerAnalytics;
use Illuminate\Support\Facades\DB;

class CustomerAnalyticsRepository implements CustomerAnalytics
{
    public function getKpis(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // 1. Agregado único de Clientes Totales y Ingreso Total en el rango de fechas
        $stats = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->selectRaw('COUNT(DISTINCT client_id) as total_customers, SUM(total_amount_usd) as total_revenue')
            ->first();

        $totalCustomers = (int) ($stats->total_customers ?? 0);
        $totalRevenue = (float) ($stats->total_revenue ?? 0);

        // 2. Conteo eficiente en SQL de Clientes con más de una compra (Recompra)
        $repurchaseCount = 0;
        if ($totalCustomers > 0) {
            $repurchaseCount = DB::table('orders')
                ->select('client_id')
                ->where('status', 'Completed')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->groupBy('client_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();
        }

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

        $newCustomersDaily = DB::table('clients')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
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

        // Agregación directa en SQL mediante subconsulta
        $sub = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->select('client_id', DB::raw('COUNT(*) as order_count'))
            ->groupBy('client_id');

        $distribution = DB::query()
            ->fromSub($sub, 'user_orders')
            ->select('order_count', DB::raw('COUNT(*) as total'))
            ->groupBy('order_count')
            ->pluck('total', 'order_count')
            ->toArray();

        return $distribution;
    }

    public function getValueSegmentation(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $customers = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->select('client_id', DB::raw('SUM(total_amount_usd) as total_spent'))
            ->groupBy('client_id')
            ->orderByDesc('total_spent')
            ->get();

        $totalCount = $customers->count();
        if ($totalCount === 0) {
            return [];
        }

        $totalRevenue = (float) $customers->sum('total_spent');

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
                'revenue' => (float) $platinum->sum('total_spent'),
                'avg_per_client' => (float) ($platinum->avg('total_spent') ?? 0),
            ],
            'gold' => [
                'count' => $gold->count(),
                'revenue' => (float) $gold->sum('total_spent'),
                'avg_per_client' => (float) ($gold->avg('total_spent') ?? 0),
            ],
            'silver' => [
                'count' => $silver->count(),
                'revenue' => (float) $silver->sum('total_spent'),
                'avg_per_client' => (float) ($silver->avg('total_spent') ?? 0),
            ],
            'bronze' => [
                'count' => $bronze->count(),
                'revenue' => (float) $bronze->sum('total_spent'),
                'avg_per_client' => (float) ($bronze->avg('total_spent') ?? 0),
            ],
            'total_revenue' => $totalRevenue,
        ];
    }

    public function getCohortData(array $filters): array
    {
        $firstPurchases = DB::table('orders')
            ->where('status', 'Completed')
            ->select('client_id', DB::raw('MIN(order_date) as first_purchase'))
            ->groupBy('client_id');

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
            ? "CAST(julianday('now') - julianday(MAX(orders.order_date)) AS INTEGER)"
            : "DATEDIFF(NOW(), MAX(orders.order_date))";

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
                DB::raw($recencyExpr . ' as recency_days')
            )
            ->groupBy('clients.id', 'clients.name', 'clients.last_name', 'clients.phone')
            ->having(DB::raw($recencyExpr), '>', 60)
            ->having(function ($query) {
                $query->having(DB::raw('COUNT(*)'), '>', 5)
                    ->orHaving(DB::raw('SUM(orders.total_amount_usd)'), '>', 100);
            })
            ->orderByDesc(DB::raw('SUM(orders.total_amount_usd)'))
            ->limit(10)
            ->get()
            ->toArray();
    }
}
