<?php

declare(strict_types=1);

namespace App\Repositories\Bi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class LaboratoryMasterReportRepository
{
    /**
     * Obtener catálogo de laboratorios o grupos corporativos.
     */
    public function getCatalogs(bool $groupByCorporate = false): Collection
    {
        if ($groupByCorporate) {
            return DB::table('groups_laboratories')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        return DB::table('laboratories')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Obtener Ranking de Laboratorios por Unidades, Valor (Venta Bruta) o Stock
     */
    public function getRankings(string $metric, int $page, array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $groupByCorporate = filter_var($filters['group_by_corporate'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $baseQuery = $metric === 'total_stock' || $metric === 'inventory_value'
            ? DB::table('products')->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            : DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        $baseQuery->leftJoin('groups_laboratories', 'laboratories.group_id', '=', 'groups_laboratories.id');

        if ($groupByCorporate) {
            $baseQuery->select(
                DB::raw('COALESCE(groups_laboratories.id, laboratories.id) as aggregation_id'),
                DB::raw('COALESCE(groups_laboratories.name, laboratories.name) as name'),
                DB::raw(($metric === 'total_stock' || $metric === 'inventory_value' ? 'SUM(products.stock)' : 'SUM(order_details.quantity)') . ' as total_units'),
                DB::raw(($metric === 'total_stock' || $metric === 'inventory_value' ? 'SUM(products.stock * products.unit_cost)' : 'SUM(order_details.quantity * order_details.unit_price_usd)') . ' as total_revenue')
            )
            ->groupBy(DB::raw('COALESCE(groups_laboratories.id, laboratories.id)'), DB::raw('COALESCE(groups_laboratories.name, laboratories.name)'));
        } else {
            $baseQuery->select(
                'laboratories.id as aggregation_id',
                'laboratories.name',
                DB::raw(($metric === 'total_stock' || $metric === 'inventory_value' ? 'SUM(products.stock)' : 'SUM(order_details.quantity)') . ' as total_units'),
                DB::raw(($metric === 'total_stock' || $metric === 'inventory_value' ? 'SUM(products.stock * products.unit_cost)' : 'SUM(order_details.quantity * order_details.unit_price_usd)') . ' as total_revenue')
            )
            ->groupBy('laboratories.id', 'laboratories.name');
        }

        $orderBy = ($metric === 'total_revenue' || $metric === 'inventory_value') ? 'total_revenue' : 'total_units';

        return $baseQuery->orderByDesc($orderBy)
            ->paginate(10, ['*'], 'page', $page);
    }

    /**
     * Obtener Rentabilidad (Margen %) por Laboratorio
     */
    public function getProfitability(array $filters): Collection
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $groupByCorporate = filter_var($filters['group_by_corporate'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoin('groups_laboratories', 'laboratories.group_id', '=', 'groups_laboratories.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($groupByCorporate) {
            $query->select(
                DB::raw('COALESCE(groups_laboratories.name, laboratories.name) as name'),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as total_revenue'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) / SUM(order_details.quantity * order_details.unit_price_usd) * 100 as margin_percent'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) as total_profit')
            )
            ->groupBy(DB::raw('COALESCE(groups_laboratories.id, laboratories.id)'), DB::raw('COALESCE(groups_laboratories.name, laboratories.name)'));
        } else {
            $query->select(
                'laboratories.name',
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as total_revenue'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) / SUM(order_details.quantity * order_details.unit_price_usd) * 100 as margin_percent'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) as total_profit')
            )
            ->groupBy('laboratories.id', 'laboratories.name');
        }

        return $query->having('margin_percent', '>', 0)
            ->orderByDesc('total_profit')
            ->limit(10)
            ->get();
    }

    /**
     * Datos de Tendencia para los Top 5 Laboratorios
     */
    public function getTrendData(array $filters): Collection
    {
        $startDate = $filters['start_date'] ?? now()->subMonths(6)->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $groupByCorporate = filter_var($filters['group_by_corporate'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // 1. Identificar los Top 5
        $top5Query = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoin('groups_laboratories', 'laboratories.group_id', '=', 'groups_laboratories.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($groupByCorporate) {
            $top5Query->select(DB::raw('COALESCE(groups_laboratories.id, laboratories.id) as master_id'))
                ->groupBy('master_id');
        } else {
            $top5Query->select('products.laboratory_id as master_id')
                ->groupBy('master_id');
        }

        $top5Ids = $top5Query->orderByDesc(DB::raw('SUM(order_details.quantity * order_details.unit_price_usd)'))
            ->limit(5)
            ->pluck('master_id');

        if ($top5Ids->isEmpty()) {
            return collect([]);
        }

        // 2. Tendencia
        $query = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoin('groups_laboratories', 'laboratories.group_id', '=', 'groups_laboratories.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($groupByCorporate) {
            $query->select(
                DB::raw('COALESCE(groups_laboratories.name, laboratories.name) as lab_name'),
                DB::raw("DATE_FORMAT(orders.created_at, '%Y-%m') as month"),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as revenue')
            )
            ->whereIn(DB::raw('COALESCE(groups_laboratories.id, laboratories.id)'), $top5Ids)
            ->groupBy('lab_name', 'month');
        } else {
            $query->select(
                'laboratories.name as lab_name',
                DB::raw("DATE_FORMAT(orders.created_at, '%Y-%m') as month"),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as revenue')
            )
            ->whereIn('products.laboratory_id', $top5Ids)
            ->groupBy('lab_name', 'month');
        }

        return $query->orderBy('month')->get();
    }

    /**
     * Stock on Hand por Laboratorio
     */
    public function getStockOnHand(array $filters): Collection
    {
        $metric = $filters['metric'] ?? 'inventory_value';
        $orderBy = $metric === 'total_stock' ? 'total_stock' : 'inventory_value';
        $groupByCorporate = filter_var($filters['group_by_corporate'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = DB::table('products')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoin('groups_laboratories', 'laboratories.group_id', '=', 'groups_laboratories.id')
            ->where('products.is_active', 1)
            ->where('products.stock', '>', 0);

        if ($groupByCorporate) {
            $query->select(
                DB::raw('COALESCE(groups_laboratories.id, laboratories.id) as aggregation_id'),
                DB::raw('COALESCE(groups_laboratories.name, laboratories.name) as name'),
                DB::raw('SUM(products.stock) as total_stock'),
                DB::raw('SUM(products.stock * products.unit_cost) as inventory_value')
            )
            ->groupBy(DB::raw('COALESCE(groups_laboratories.id, laboratories.id)'), DB::raw('COALESCE(groups_laboratories.name, laboratories.name)'));
        } else {
            $query->select(
                'laboratories.id as aggregation_id',
                'laboratories.name',
                DB::raw('SUM(products.stock) as total_stock'),
                DB::raw('SUM(products.stock * products.unit_cost) as inventory_value')
            )
            ->groupBy('laboratories.id', 'laboratories.name');
        }

        return $query->orderByDesc($orderBy)->limit(10)->get();
    }

    /**
     * Deep Dive de un Laboratorio específico (Limitado a Top 50 productos para evitar payload masivo)
     */
    public function getLaboratoryDetails(int $id, array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $groupByCorporate = filter_var($filters['group_by_corporate'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $queryBase = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($groupByCorporate) {
            $queryBase->where(function($q) use ($id) {
                $q->where('laboratories.group_id', $id)
                  ->orWhere(function($sq) use ($id) {
                      $sq->whereNull('laboratories.group_id')->where('laboratories.id', $id);
                  });
            });
        } else {
            $queryBase->where('products.laboratory_id', $id);
        }

        // Top 50 productos con ventas en el periodo
        $topProducts = (clone $queryBase)
            ->select(
                'products.id',
                'products.name',
                'products.group_id',
                DB::raw('SUM(order_details.quantity) as units'),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as revenue'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) as estimated_margin')
            )
            ->groupBy('products.id', 'products.name', 'products.group_id')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get();

        // Ventas por grupo de productos
        $groupPerformance = (clone $queryBase)
            ->join('groups_products', 'products.group_id', '=', 'groups_products.id')
            ->select(
                'groups_products.id',
                'groups_products.name',
                DB::raw('SUM(order_details.quantity) as units'),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as revenue')
            )
            ->groupBy('groups_products.id', 'groups_products.name')
            ->get();

        // KPIs Generales
        $stats = (clone $queryBase)
            ->select(
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) / NULLIF(COUNT(DISTINCT orders.id), 0) as avg_ticket'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) / NULLIF(SUM(order_details.quantity * order_details.unit_price_usd), 0) * 100 as avg_margin_percent')
            )
            ->first();

        return [
            'top_products' => $topProducts,
            'group_performance' => $groupPerformance,
            'stats' => $stats
        ];
    }
}
