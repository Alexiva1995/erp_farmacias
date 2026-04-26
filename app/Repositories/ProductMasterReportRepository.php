<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProductMasterReportRepositoryInterface;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductMasterReportRepository implements ProductMasterReportRepositoryInterface
{
    public function getPerformanceData(array $filters): Collection
    {
        $startDate = $filters['start_date'] ?? '2026-04-01';
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        return DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'products.id',
                'products.name',
                'products.active_ingredient',
                'laboratories.name as laboratory_name',
                DB::raw('SUM(CASE 
                    WHEN order_details.unit_price_usd > 0 OR order_details.price > 0 
                    THEN order_details.quantity 
                    ELSE 0 
                END) as total_sold'),
                DB::raw('SUM(order_details.quantity * COALESCE(
                    NULLIF(order_details.unit_price_usd, 0), 
                    CASE WHEN orders.currency = \'USD\' THEN order_details.price ELSE (order_details.price / NULLIF(orders.usd_conversion, 0)) END,
                    0
                )) as total_revenue'),
                DB::raw('SUM(order_details.quantity * (COALESCE(
                    NULLIF(order_details.unit_price_usd, 0), 
                    CASE WHEN orders.currency = \'USD\' THEN order_details.price ELSE (order_details.price / NULLIF(orders.usd_conversion, 0)) END,
                    0
                ) - COALESCE(order_details.unit_cost, 0))) as total_margin')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($filters['laboratory_id'] ?? null, fn($q, $id) => $q->where('products.laboratory_id', $id))
            ->when($filters['group_id'] ?? null, fn($q, $id) => $q->where('products.group_id', $id))
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('products.name', 'like', "%{$s}%"))
            ->groupBy('products.id', 'products.name', 'products.active_ingredient', 'laboratories.name')
            ->get();
    }

    public function getLaboratoryRanking(array $filters): Collection
    {
        $startDate = $filters['start_date'] ?? '2026-04-01';
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        return DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'laboratories.name',
                DB::raw('SUM(order_details.quantity * (CASE 
                    WHEN order_details.unit_price_usd > 0 THEN order_details.unit_price_usd 
                    WHEN orders.currency = \'USD\' THEN order_details.price 
                    ELSE (order_details.price / NULLIF(orders.usd_conversion, 0)) 
                END - order_details.unit_cost)) as total_margin')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('laboratories.id', 'laboratories.name')
            ->orderByDesc('total_margin')
            ->limit(10)
            ->get();
    }

    public function getAbcSummary(array $filters): Collection
    {
        $query = DB::table('products')
            ->select(
                'id',
                DB::raw('(stock * unit_cost) as inventory_value')
            )
            ->where('is_active', 1)
            ->when($filters['laboratory_id'] ?? null, fn($q, $id) => $q->where('laboratory_id', $id))
            ->when($filters['group_id'] ?? null, fn($q, $id) => $q->where('group_id', $id));

        $data = $query->get();
        $totalValue = $data->sum('inventory_value');
        
        if ($totalValue == 0) return collect();

        $sorted = $data->sortByDesc('inventory_value');
        $runningSum = 0;
        
        return $sorted->map(function($item) use ($totalValue, &$runningSum) {
            $item = (array) $item;
            $runningSum += $item['inventory_value'];
            $percent = ($runningSum / $totalValue) * 100;
            if ($percent <= 80) $item['abc'] = 'A';
            elseif ($percent <= 95) $item['abc'] = 'B';
            else $item['abc'] = 'C';
            return $item;
        })->groupBy('abc')->map(function($group, $key) {
            return [
                'type' => $key,
                'count' => $group->count(),
                'revenue' => $group->sum('inventory_value')
            ];
        })->values();
    }

    public function getCrossSellingData(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $page = $filters['page'] ?? 1;

        return DB::table('order_details as od1')
            ->join('order_details as od2', function($join) {
                $join->on('od1.order_id', '=', 'od2.order_id')
                     ->on('od1.product_id', '<', 'od2.product_id');
            })
            ->join('orders', 'od1.order_id', '=', 'orders.id')
            ->join('products as p1', 'od1.product_id', '=', 'p1.id')
            ->join('products as p2', 'od2.product_id', '=', 'p2.id')
            ->leftJoin('laboratories as l1', 'p1.laboratory_id', '=', 'l1.id')
            ->leftJoin('laboratories as l2', 'p2.laboratory_id', '=', 'l2.id')
            ->select(
                'od1.product_id as product_id_a',
                'od2.product_id as product_id_b',
                'p1.name as product_a',
                'p2.name as product_b',
                'p1.active_ingredient as ingredient_a',
                'p2.active_ingredient as ingredient_b',
                'l1.name as lab_a',
                'l2.name as lab_b',
                DB::raw('COUNT(*) as frequency')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('od1.product_id', 'od2.product_id', 'p1.name', 'p2.name', 'p1.active_ingredient', 'p2.active_ingredient', 'l1.name', 'l2.name')
            ->orderByDesc('frequency')
            ->paginate(8, ['*'], 'page', $page);
    }

    public function getSupplyIntelligence(array $filters): Collection
    {
        // Out of stock y Días de inventario
        return DB::table('products')
            ->select(
                'id',
                'name',
                'stock',
                'sales_average', // Asumimos que este campo existe según describe previo
                DB::raw('CASE WHEN sales_average > 0 THEN stock / sales_average ELSE 999 END as days_remaining')
            )
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->get();
    }

    public function getTrendComparison(array $filters): Collection
    {
        $productId = $filters['product_id'] ?? null;
        $startDate = now()->subMonths(12)->startOfMonth()->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $querySales = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->select(
                DB::raw('DATE_FORMAT(orders.created_at, "%Y-%u") as week'),
                DB::raw('SUM(order_details.quantity) as qty_sold'),
                DB::raw('0 as qty_purchased')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('week');

        $queryPurchases = DB::table('invoice_details')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%u") as week'),
                DB::raw('0 as qty_sold'),
                DB::raw('SUM(quantity) as qty_purchased')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('week');

        if ($productId) {
            $querySales->where('product_id', $productId);
            $queryPurchases->where('product_id', $productId);
        }

        return $querySales->unionAll($queryPurchases)
            ->get()
            ->groupBy('week')
            ->map(function($weekData, $week) {
                return [
                    'week' => $week,
                    'sold' => $weekData->sum('qty_sold'),
                    'purchased' => $weekData->sum('qty_purchased')
                ];
            })->sortBy('week')->values();
    }
    public function getRankingsData(array $filters)
    {
        $startDate = $filters['start_date'] ?? '2026-04-01';
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $sortBy = $filters['sort_by'] ?? 'total_sold';
        $page = $filters['page'] ?? 1;

        return DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'products.id',
                'products.name',
                'products.active_ingredient',
                'laboratories.name as laboratory_name',
                DB::raw('SUM(order_details.quantity) as total_sold'),
                DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as total_revenue'),
                DB::raw('SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) as total_margin')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($filters['laboratory_id'] ?? null, fn($q, $id) => $q->where('products.laboratory_id', $id))
            ->when($filters['group_id'] ?? null, fn($q, $id) => $q->where('products.group_id', $id))
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('products.name', 'like', "%{$s}%"))
            ->groupBy('products.id', 'products.name', 'products.active_ingredient', 'laboratories.name')
            ->orderByDesc($sortBy)
            ->paginate(10, ['*'], 'page', $page);
    }
}
