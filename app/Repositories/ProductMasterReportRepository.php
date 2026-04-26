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
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_details.quantity) as total_sold'),
                DB::raw('SUM(order_details.quantity * CASE 
                    WHEN order_details.unit_price_usd > 0 THEN order_details.unit_price_usd 
                    WHEN orders.currency = \'USD\' THEN order_details.price 
                    ELSE (order_details.price / NULLIF(orders.usd_conversion, 0)) 
                END) as total_revenue'),
                DB::raw('SUM(order_details.quantity * (CASE 
                    WHEN order_details.unit_price_usd > 0 THEN order_details.unit_price_usd 
                    WHEN orders.currency = \'USD\' THEN order_details.price 
                    ELSE (order_details.price / NULLIF(orders.usd_conversion, 0)) 
                END - order_details.unit_cost)) as total_margin')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($filters['laboratory_id'] ?? null, fn($q, $id) => $q->where('products.laboratory_id', $id))
            ->when($filters['group_id'] ?? null, fn($q, $id) => $q->where('products.group_id', $id))
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('products.name', 'like', "%{$s}%"))
            ->groupBy('products.id', 'products.name')
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
        // Reutilizamos el concepto de ABC pero resumido
        // Nota: Para simplificar, calculamos A, B, C aquí basado en ingresos históricos
        $data = $this->getPerformanceData($filters);
        $totalRevenue = $data->sum('total_revenue');
        
        if ($totalRevenue == 0) return collect();

        $sorted = $data->sortByDesc('total_revenue');
        $runningSum = 0;
        
        return $sorted->map(function($item) use ($totalRevenue, &$runningSum) {
            $item = (array) $item;
            $runningSum += $item['total_revenue'];
            $percent = ($runningSum / $totalRevenue) * 100;
            if ($percent <= 80) $item['abc'] = 'A';
            elseif ($percent <= 95) $item['abc'] = 'B';
            else $item['abc'] = 'C';
            return $item;
        })->groupBy('abc')->map(function($group, $key) {
            return [
                'type' => $key,
                'count' => $group->count(),
                'revenue' => $group->sum('total_revenue')
            ];
        })->values();
    }

    public function getExpirationsData(): Collection
    {
        return DB::table('product_lots')
            ->select(
                DB::raw("CASE 
                    WHEN expiration_date < CURDATE() THEN 'Vencido'
                    WHEN expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Crítico (<30d)'
                    WHEN expiration_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'Próximo (30-90d)'
                    ELSE 'Seguro (>90d)'
                END as bucket"),
                DB::raw('SUM(quantity * unit_cost) as total_value'),
                DB::raw('COUNT(*) as lots_count')
            )
            ->where('quantity', '>', 0)
            ->groupBy('bucket')
            ->get();
    }

    public function getInventoryDiscrepancies(array $filters): Collection
    {
        return DB::table('product_counts')
            ->join('inventory_cycles', 'product_counts.cycle_id', '=', 'inventory_cycles.id')
            ->select(
                DB::raw('DATE(product_counts.created_at) as date'),
                DB::raw('SUM(ABS(discrepancy)) as units_discrepancy'),
                DB::raw('SUM(ABS(discrepancy) * (SELECT unit_cost FROM products WHERE id = product_counts.product_id)) as money_loss')
            )
            ->where('product_counts.status', 'approved')
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();
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
        $groupId = $filters['group_id'] ?? null;

        $querySales = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select(
                DB::raw('DATE_FORMAT(orders.created_at, "%Y-%u") as week'),
                DB::raw('SUM(order_details.quantity) as qty_sold'),
                DB::raw('0 as qty_purchased')
            )
            ->where('orders.status', 'Completed');

        $queryPurchases = DB::table('invoice_details')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%u") as week'),
                DB::raw('0 as qty_sold'),
                DB::raw('SUM(quantity) as qty_purchased')
            );

        if ($productId) {
            $querySales->where('product_id', $productId);
            $queryPurchases->where('product_id', $productId);
        }

        if ($groupId) {
            $querySales->where('products.group_id', $groupId);
            $queryPurchases->join('products', 'invoice_details.product_id', '=', 'products.id')
                           ->where('products.group_id', $groupId);
        }

        $sales = $querySales->groupBy('week')->get();
        $purchases = $queryPurchases->groupBy('week')->get();

        return $sales->concat($purchases)->groupBy('week')->map(function($weekData, $week) {
            return [
                'week' => $week,
                'sold' => $weekData->sum('qty_sold'),
                'purchased' => $weekData->sum('qty_purchased')
            ];
        })->sortBy('week')->values();
    }
}
