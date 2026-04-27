<?php

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
        $avgTicket = $completedSales > 0 ? $totalRevenue / $completedSales : 0;

        // Promedio Venta Diario
        $diffDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $avgDailySales = $totalRevenue / $diffDays;

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

        return [
            'completed_sales' => $completedSales,
            'abandoned_sales' => $abandonedSales,
            'quotations_generated' => $quotationsCount,
            'conversion_rate' => round($conversionRate, 2),
            'avg_ticket' => round($avgTicket, 2),
            'avg_daily_sales' => round($avgDailySales, 2),
            'total_revenue' => round($totalRevenue, 2)
        ];
    }

    public function getTemporalAnalysis(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // foco diario (promedio por día de la semana)
        $dailyFocus = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                DB::raw('DAYNAME(created_at) as day_name'),
                DB::raw('DAYOFWEEK(created_at) as day_index'),
                DB::raw('AVG(total_amount_usd) as avg_amount')
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

        return [
            'daily_focus' => $dailyFocus,
            'hourly_slots' => $hourlySlots
        ];
    }

    public function getSegmentation(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // 1. Unidades por ticket
        $byUnits = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('orders.id', DB::raw('SUM(order_details.quantity) as total_qty'))
            ->groupBy('orders.id')
            ->get();

        $unitRanges = [
            '1 Producto' => $byUnits->where('total_qty', 1)->count(),
            '2-3 Productos' => $byUnits->whereBetween('total_qty', [2, 3])->count(),
            '4-6 Productos' => $byUnits->whereBetween('total_qty', [4, 6])->count(),
            '> 6 Productos' => $byUnits->where('total_qty', '>', 6)->count(),
        ];

        // 2. Valor monetario ($)
        $byValue = DB::table('orders')
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('total_amount_usd')
            ->get();

        $valueRanges = [
            'Venta Menor (<20$)' => $byValue->where('total_amount_usd', '<', 20)->count(),
            'Venta Media (20-100$)' => $byValue->whereBetween('total_amount_usd', [20, 100])->count(),
            'Venta Mayor (>100$)' => $byValue->where('total_amount_usd', '>', 100)->count(),
        ];

        return [
            'units' => $unitRanges,
            'monetary' => $valueRanges
        ];
    }
}
