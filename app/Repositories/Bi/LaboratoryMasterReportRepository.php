<?php

namespace App\Repositories\Bi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class LaboratoryMasterReportRepository
{
    /**
     * Obtener Ranking de Laboratorios por Unidades o Valor (Venta Bruta)
     */
    public function getRankings(string $metric, int $page, array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $groupByCorporate = filter_var($filters['group_by_corporate'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                $groupByCorporate 
                    ? DB::raw('COALESCE(laboratories.parent_id, laboratories.id) as aggregation_id')
                    : 'laboratories.id as aggregation_id',
                $groupByCorporate
                    ? DB::raw('(SELECT name FROM laboratories as l2 WHERE l2.id = COALESCE(laboratories.parent_id, laboratories.id)) as name')
                    : 'laboratories.name',
                DB::raw('SUM(order_details.quantity) as total_units'),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as total_revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as ticket_count')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($groupByCorporate) {
            $query->groupBy(DB::raw('COALESCE(laboratories.parent_id, laboratories.id)'));
        } else {
            $query->groupBy('laboratories.id', 'laboratories.name');
            // Si no estamos agrupando por corporativo, pero el usuario filtró por un padre específico
            if (!empty($filters['parent_id'])) {
                $query->where('laboratories.parent_id', $filters['parent_id']);
            }
        }

        $orderBy = $metric === 'total_revenue' ? 'total_revenue' : 'total_units';

        return $query->orderByDesc($orderBy)
            ->paginate(10, ['*'], 'page', $page);
    }

    /**
     * Obtener Rentabilidad (Margen %) por Laboratorio
     */
    public function getProfitability(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        return DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'laboratories.name',
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) / SUM(order_details.quantity * order_details.unit_price_usd) * 100 as margin_percent'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) as total_profit')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('laboratories.id', 'laboratories.name')
            ->having('margin_percent', '>', 0)
            ->orderByDesc('margin_percent')
            ->limit(10)
            ->get();
    }

    /**
     * Datos de Tendencia para los Top 5 Laboratorios
     */
    public function getTrendData(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->subMonths(6)->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // 1. Identificar los Top 5 laboratorios del periodo
        $top5Ids = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select('products.laboratory_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('products.laboratory_id')
            ->orderByDesc(DB::raw('SUM(order_details.quantity * order_details.unit_price_usd)'))
            ->limit(5)
            ->pluck('products.laboratory_id');

        if ($top5Ids->isEmpty()) return collect([]);

        // 2. Obtener tendencia mensual para esos 5
        return DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'laboratories.name as lab_name',
                DB::raw("DATE_FORMAT(orders.created_at, '%Y-%m') as month"),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as revenue')
            )
            ->whereIn('products.laboratory_id', $top5Ids)
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('lab_name', 'month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Stock on Hand por Laboratorio (Treemap) - BASADO EN COSTO
     */
    public function getStockOnHand(array $filters)
    {
        return DB::table('products')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'laboratories.name',
                DB::raw('SUM(products.stock) as total_stock'),
                DB::raw('SUM(products.stock * products.unit_cost) as inventory_value')
            )
            ->where('products.is_active', 1)
            ->where('products.stock', '>', 0)
            ->groupBy('laboratories.id', 'laboratories.name')
            ->orderByDesc('inventory_value')
            ->limit(20)
            ->get();
    }

    /**
     * Deep Dive de un Laboratorio específico
     */
    public function getLaboratoryDetails(int $labId, array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // Top 20 productos
        $topProducts = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_details.quantity) as units'),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as revenue'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) as estimated_margin')
            )
            ->where('products.laboratory_id', $labId)
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get();

        // KPIs Generales
        $stats = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select(
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) / COUNT(DISTINCT orders.id) as avg_ticket'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) / SUM(order_details.quantity * order_details.unit_price_usd) * 100 as avg_margin_percent')
            )
            ->where('products.laboratory_id', $labId)
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->first();

        return [
            'top_products' => $topProducts,
            'stats' => $stats
        ];
    }
}
