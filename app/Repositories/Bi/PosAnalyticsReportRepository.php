<?php

declare(strict_types=1);

namespace App\Repositories\Bi;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PosAnalyticsReportRepository
{
    public function getKpis(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $baseQuery = DB::table('orders')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        $completedSales = (clone $baseQuery)->where('status', 'Completed')->count();
        $abandonedSales = (clone $baseQuery)->whereIn('status', ['Cancelled', 'Abandoned'])->count();
        
        $totalRevenue = (clone $baseQuery)->where('status', 'Completed')->sum('total_amount_usd');
        
        $quotationsCount = DB::table('quotations')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->count();

        // Ticket Promedio
        $avgTicket = $completedSales > 0 ? (float)$totalRevenue / $completedSales : 0.0;

        // Promedio Venta Diario
        $diffDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $avgDailySales = (float)$totalRevenue / $diffDays;

        // Tasa Conversión (Simplificada: Monto y Cliente coincidente en el periodo)
        // Nota: Esta es una aproximación ya que no hay FK directa.
        $convertedQuotations = DB::table('quotations')
            ->whereBetween('quotations.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereExists(function ($query) use ($startDate, $endDate) {
                $query->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.client_id', 'quotations.client_id')
                    ->whereColumn('orders.total_amount_usd', 'quotations.total')
                    ->where('orders.status', 'Completed')
                    ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->count();
        
        $conversionRate = $quotationsCount > 0 ? ($convertedQuotations / $quotationsCount) * 100 : 0;

        // Venta Cruzada (Tickets con > 1 unidad física total)
        $crossSellingCount = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('orders.id', DB::raw('SUM(order_details.quantity) as total_qty'))
            ->groupBy('orders.id')
            ->having('total_qty', '>', 1)
            ->get()
            ->count();

        $crossSellingRate = $completedSales > 0 ? ($crossSellingCount / $completedSales) * 100 : 0;

        return [
            'completed_sales' => (int)$completedSales,
            'abandoned_sales' => (int)$abandonedSales,
            'quotations_generated' => (int)$quotationsCount,
            'conversion_rate' => round((float)$conversionRate, 2),
            'avg_ticket' => round((float)$avgTicket, 2),
            'avg_daily_sales' => round((float)$avgDailySales, 2),
            'total_revenue' => round((float)$totalRevenue, 2),
            'cross_selling_count' => (int)$crossSellingCount,
            'cross_selling_rate' => round((float)$crossSellingRate, 2)
        ];
    }

    public function getTemporalAnalysis(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // Rendimiento diario (Suma total por día de la semana)
        $dailyFocus = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('DAYNAME(created_at) as day_name'),
                DB::raw('DAYOFWEEK(created_at) as day_index'),
                DB::raw('SUM(total_amount_usd) as total_revenue')
            )
            ->groupBy('day_name', 'day_index')
            ->orderBy('day_index')
            ->get();

        // franjas horarias
        $hourlySlots = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount_usd) as revenue')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Top Vendedores por Hora
        $topSellersByHour = DB::table('orders')
            ->leftJoin('users', 'users.id', '=', 'orders.seller_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('HOUR(orders.created_at) as hr'),
                DB::raw('COALESCE(users.username, "S/V") as seller_name'),
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
            'daily_focus' => $dailyFocus,
            'hourly_slots' => $hourlySlots,
            'top_sellers' => $topSellersByHour
        ];
    }

    public function getSegmentation(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // 1. Unidades por ticket (Agregados y agrupados directamente en la consulta)
        $unitsQuery = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
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

        // 2. Valor monetario ($) (Calculado directamente con condicionales CASE)
        $valueStats = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
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
            'monetary' => $valueRanges
        ];
    }
}
