<?php

declare(strict_types=1);

namespace App\Repositories\Bi;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PosAnalyticsReportRepository
{
    public function getKpis(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $sellerId = !empty($filters['seller_id']) ? (int)$filters['seller_id'] : null;

        $ordersQuery = DB::table('orders')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('seller_id', $sellerId));

        $orderStats = (clone $ordersQuery)
            ->select(
                DB::raw("COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_sales"),
                DB::raw("COUNT(CASE WHEN status IN ('Cancelled', 'Abandoned') THEN 1 END) as abandoned_sales"),
                DB::raw("SUM(CASE WHEN status = 'Completed' THEN total_amount_usd ELSE 0 END) as total_revenue"),
                DB::raw("COUNT(DISTINCT CASE WHEN status = 'Completed' THEN DATE(created_at) END) as operational_days")
            )
            ->first();

        $completedSales = (int)($orderStats->completed_sales ?? 0);
        $abandonedSales = (int)($orderStats->abandoned_sales ?? 0);
        $totalRevenue = (float)($orderStats->total_revenue ?? 0.0);
        $operationalDays = (int)($orderStats->operational_days ?? 0);
        
        $quotationsQuery = DB::table('quotations')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('user_id', $sellerId));

        $quotationsCount = $quotationsQuery->count();

        // Ticket Promedio
        $avgTicket = $completedSales > 0 ? $totalRevenue / $completedSales : 0.0;

        // Promedio Venta Diario basado en días operativos reales (con fallback a días del rango)
        $diffDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $divisorDays = $operationalDays > 0 ? $operationalDays : $diffDays;
        $avgDailySales = $totalRevenue / ($divisorDays ?: 1);

        // Tasa Conversión (Coincidencia por cliente y total en periodo)
        $convertedQuotations = DB::table('quotations')
            ->whereBetween('quotations.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('quotations.user_id', $sellerId))
            ->whereExists(function ($query) use ($startDate, $endDate, $sellerId) {
                $query->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.client_id', 'quotations.client_id')
                    ->whereColumn('orders.total_amount_usd', 'quotations.total')
                    ->where('orders.status', 'Completed')
                    ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->when($sellerId, fn($q) => $q->where('orders.seller_id', $sellerId));
            })
            ->count();
        
        $conversionRate = $quotationsCount > 0 ? ($convertedQuotations / $quotationsCount) * 100 : 0;

        // Venta Cruzada (Órdenes completadas con más de 1 artículo)
        $crossSellingQuery = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('orders.seller_id', $sellerId))
            ->select('orders.id')
            ->groupBy('orders.id')
            ->havingRaw('SUM(order_details.quantity) > 1');

        $crossSellingCount = DB::table(DB::raw("({$crossSellingQuery->toSql()}) as cross_orders"))
            ->mergeBindings($crossSellingQuery)
            ->count();

        $crossSellingRate = $completedSales > 0 ? ($crossSellingCount / $completedSales) * 100 : 0;

        // Unidades totales y descuentos otorgados
        $detailStats = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('orders.seller_id', $sellerId))
            ->select(
                DB::raw('COALESCE(SUM(order_details.quantity), 0) as total_units'),
                DB::raw('COALESCE(SUM(CASE WHEN order_details.discount_percentage > 0 THEN ((order_details.price_before_discount - order_details.price) * order_details.quantity) ELSE 0 END), 0) as discount_total')
            )
            ->first();

        $totalUnits = (float)($detailStats->total_units ?? 0);
        $unitsPerTransaction = $completedSales > 0 ? round($totalUnits / $completedSales, 2) : 0.0;
        $discountTotal = (float)($detailStats->discount_total ?? 0.0);

        return [
            'completed_sales' => $completedSales,
            'abandoned_sales' => $abandonedSales,
            'quotations_generated' => (int)$quotationsCount,
            'conversion_rate' => round((float)$conversionRate, 2),
            'avg_ticket' => round((float)$avgTicket, 2),
            'avg_daily_sales' => round((float)$avgDailySales, 2),
            'total_revenue' => round((float)$totalRevenue, 2),
            'cross_selling_count' => (int)$crossSellingCount,
            'cross_selling_rate' => round((float)$crossSellingRate, 2),
            'total_units' => round($totalUnits, 0),
            'units_per_transaction' => $unitsPerTransaction,
            'discount_total' => round($discountTotal, 2),
            'operational_days' => $operationalDays,
        ];
    }

    public function getTemporalAnalysis(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $sellerId = !empty($filters['seller_id']) ? (int)$filters['seller_id'] : null;

        // 1. Tendencia diaria continua (Timeline por fecha)
        $dailyTrend = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('seller_id', $sellerId))
            ->select(
                DB::raw('DATE(created_at) as sale_date'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount_usd) as total_revenue')
            )
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        // 2. Rendimiento diario agregado (Suma total por día de la semana)
        $dailyFocus = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('seller_id', $sellerId))
            ->select(
                DB::raw('DAYNAME(created_at) as day_name'),
                DB::raw('DAYOFWEEK(created_at) as day_index'),
                DB::raw('SUM(total_amount_usd) as total_revenue')
            )
            ->groupBy('day_name', 'day_index')
            ->orderBy('day_index')
            ->get();

        // 3. Franjas horarias
        $hourlySlots = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('seller_id', $sellerId))
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount_usd) as revenue')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // 4. Top Vendedores por Hora
        $topSellersByHour = DB::table('orders')
            ->leftJoin('users', 'users.id', '=', 'orders.seller_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('orders.seller_id', $sellerId))
            ->select(
                DB::raw('HOUR(orders.created_at) as hr'),
                DB::raw('COALESCE(users.username, users.name, "S/V") as seller_name'),
                DB::raw('SUM(orders.total_amount_usd) as revenue')
            )
            ->groupBy('hr', 'seller_name')
            ->get()
            ->groupBy('hr')
            ->mapWithKeys(function ($group, $key) {
                return [$key => $group->sortByDesc('revenue')->first()];
            })
            ->toArray();

        return [
            'daily_trend' => $dailyTrend,
            'daily_focus' => $dailyFocus,
            'hourly_slots' => $hourlySlots,
            'top_sellers' => $topSellersByHour,
        ];
    }

    public function getSegmentation(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $sellerId = !empty($filters['seller_id']) ? (int)$filters['seller_id'] : null;

        // 1. Unidades por ticket
        $unitsQuery = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('orders.seller_id', $sellerId))
            ->select('orders.id', DB::raw('SUM(order_details.quantity) as total_qty'))
            ->groupBy('orders.id');

        $unitStats = DB::table(DB::raw("({$unitsQuery->toSql()}) as order_qtys"))
            ->mergeBindings($unitsQuery)
            ->select(
                DB::raw("COUNT(CASE WHEN total_qty = 1 THEN 1 END) as qty_1"),
                DB::raw("COUNT(CASE WHEN total_qty BETWEEN 2 AND 3 THEN 1 END) as qty_2_3"),
                DB::raw("COUNT(CASE WHEN total_qty BETWEEN 4 AND 6 THEN 1 END) as qty_4_6"),
                DB::raw("COUNT(CASE WHEN total_qty > 6 THEN 1 END) as qty_above_6")
            )
            ->first();

        $unitRanges = [
            '1 Producto' => (int)($unitStats->qty_1 ?? 0),
            '2-3 Productos' => (int)($unitStats->qty_2_3 ?? 0),
            '4-6 Productos' => (int)($unitStats->qty_4_6 ?? 0),
            '> 6 Productos' => (int)($unitStats->qty_above_6 ?? 0),
        ];

        // 2. Valor monetario ($)
        $valueStats = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($sellerId, fn($q) => $q->where('seller_id', $sellerId))
            ->select(
                DB::raw("COUNT(CASE WHEN total_amount_usd <= 2 THEN 1 END) as val_0_2"),
                DB::raw("COUNT(CASE WHEN total_amount_usd > 2 AND total_amount_usd <= 5 THEN 1 END) as val_2_5"),
                DB::raw("COUNT(CASE WHEN total_amount_usd > 5 AND total_amount_usd <= 10 THEN 1 END) as val_5_10"),
                DB::raw("COUNT(CASE WHEN total_amount_usd > 10 AND total_amount_usd <= 15 THEN 1 END) as val_10_15"),
                DB::raw("COUNT(CASE WHEN total_amount_usd > 15 THEN 1 END) as val_above_15")
            )
            ->first();

        $valueRanges = [
            '0-2'   => (int)($valueStats->val_0_2 ?? 0),
            '2-5'   => (int)($valueStats->val_2_5 ?? 0),
            '5-10'  => (int)($valueStats->val_5_10 ?? 0),
            '10-15' => (int)($valueStats->val_10_15 ?? 0),
            '+15'   => (int)($valueStats->val_above_15 ?? 0),
        ];

        return [
            'units' => $unitRanges,
            'monetary' => $valueRanges,
        ];
    }

    public function getFilterOptions(): array
    {
        $sellers = DB::table('users')
            ->select('id', DB::raw('COALESCE(username, name, email) as name'))
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.seller_id', 'users.id');
            })
            ->orderBy('name')
            ->get()
            ->toArray();

        return [
            'sellers' => $sellers,
        ];
    }
}
