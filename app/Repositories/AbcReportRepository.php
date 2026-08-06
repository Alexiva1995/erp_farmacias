<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\AbcReportRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Clase AbcReportRepository
 * 
 * Se encarga de la lógica de base de datos para recuperar ventas,
 * márgenes de ganancia e historial de rotación para calcular el ABC Multicriterio.
 */
class AbcReportRepository implements AbcReportRepositoryInterface
{
    /**
     * Obtener los datos agregados para el cálculo del reporte ABC Multicriterio.
     * Utiliza las tablas orders, order_details, products y categories.
     *
     * @param array $filtros
     * @return Collection
     */
    public function getAggregatedData(array $filtros): Collection
    {
        $startDate = $filtros['start_date'] ?? now()->subDays(90)->startOfDay()->format('Y-m-d H:i:s');
        $endDate = $filtros['end_date'] ?? now()->endOfDay()->format('Y-m-d H:i:s');

        // Subconsulta para calcular la rotación (Desviación Típica de ventas diarias por ítem)
        $dailySalesQuery = DB::table('order_details')
            ->select(
                'order_details.product_id',
                'order_details.dish_id',
                DB::raw('DATE(orders.order_date) as order_date_only'),
                DB::raw('SUM(order_details.quantity) as daily_quantity')
            )
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.order_date', [$startDate, $endDate])
            ->groupBy('order_details.product_id', 'order_details.dish_id', 'order_date_only');

        $varianceSubquery = DB::table(DB::raw("({$dailySalesQuery->toSql()}) as daily_sales"))
            ->mergeBindings($dailySalesQuery)
            ->select(
                'daily_sales.product_id',
                'daily_sales.dish_id',
                DB::raw('STDDEV(daily_sales.daily_quantity) as std_dev_sales'),
                DB::raw('AVG(daily_sales.daily_quantity) as avg_daily_sales')
            )
            ->groupBy('daily_sales.product_id', 'daily_sales.dish_id');

        // Subconsulta unificada de Ventas, Márgenes y Fecha de Última Venta
        $salesSubquery = DB::table('order_details')
            ->select(
                'order_details.product_id',
                'order_details.dish_id',
                DB::raw('SUM(order_details.quantity) as sold_units'),
                DB::raw('SUM(order_details.quantity * CASE WHEN order_details.unit_price_usd > 0 THEN order_details.unit_price_usd WHEN orders.currency = \'USD\' THEN order_details.price ELSE (order_details.price / NULLIF(orders.usd_conversion, 0)) END) as total_sales'),
                DB::raw('SUM(order_details.quantity * order_details.unit_cost) as total_cost'),
                DB::raw('MAX(orders.order_date) as last_sale_date')
            )
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.order_date', [$startDate, $endDate])
            ->groupBy('order_details.product_id', 'order_details.dish_id');

        // Consulta 1: Productos de Inventario
        $productsQuery = DB::table('products')
            ->select(
                'products.id',
                'products.name as product_name',
                'laboratories.name as laboratory_name',
                'products.stock as current_stock',
                'products.unit_cost as last_cost',
                DB::raw('COALESCE(products.sales_average, 0) as sales_average'),
                DB::raw('COALESCE(sales.sold_units, 0) as sold_units'),
                DB::raw('COALESCE(sales.total_sales, 0) as total_sales'),
                DB::raw('COALESCE(sales.total_cost, 0) as total_cost'),
                DB::raw('COALESCE(variance.std_dev_sales, 0) as std_dev_sales'),
                DB::raw('COALESCE(variance.avg_daily_sales, 0) as avg_daily_sales'),
                'sales.last_sale_date as last_sale_date',
                DB::raw("'product' as item_type")
            )
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoinSub($salesSubquery, 'sales', function($join) {
                $join->on('products.id', '=', 'sales.product_id');
            })
            ->leftJoinSub($varianceSubquery, 'variance', function($join) {
                $join->on('products.id', '=', 'variance.product_id');
            });

        $analysisType = $filtros['analysis_type'] ?? 'all';

        if ($analysisType === 'dead_stock') {
            $productsQuery->where('products.stock', '>', 0)
                ->where(function($q) {
                    $q->whereNull('sales.sold_units')
                      ->orWhere('sales.sold_units', '<=', 0);
                });
        } else {
            $productsQuery->where(function($q) {
                $q->where('sales.sold_units', '>', 0)
                  ->orWhere('products.stock', '>', 0);
            });
        }

        if (!empty($filtros['laboratory_id'])) {
            $productsQuery->whereIn('products.laboratory_id', (array) $filtros['laboratory_id']);
        }

        if (!empty($filtros['search'])) {
            $term = '%' . trim((string) $filtros['search']) . '%';
            $productsQuery->where(function($q) use ($term) {
                $q->where('products.name', 'like', $term)
                  ->orWhere('products.id', 'like', $term)
                  ->orWhere('laboratories.name', 'like', $term);
            });
        }

        // Consulta 2: Platos de Menú (Dishes)
        $dishesQuery = DB::table('dishes')
            ->select(
                'dishes.id',
                'dishes.name as product_name',
                'categories.name as laboratory_name',
                DB::raw('0 as current_stock'),
                'dishes.cost_price as last_cost',
                DB::raw('0 as sales_average'),
                DB::raw('COALESCE(sales.sold_units, 0) as sold_units'),
                DB::raw('COALESCE(sales.total_sales, 0) as total_sales'),
                DB::raw('COALESCE(sales.total_cost, 0) as total_cost'),
                DB::raw('COALESCE(variance.std_dev_sales, 0) as std_dev_sales'),
                DB::raw('COALESCE(variance.avg_daily_sales, 0) as avg_daily_sales'),
                'sales.last_sale_date as last_sale_date',
                DB::raw("'dish' as item_type")
            )
            ->leftJoin('categories', 'dishes.category_id', '=', 'categories.id')
            ->leftJoinSub($salesSubquery, 'sales', function($join) {
                $join->on('dishes.id', '=', 'sales.dish_id');
            })
            ->leftJoinSub($varianceSubquery, 'variance', function($join) {
                $join->on('dishes.id', '=', 'variance.dish_id');
            });

        if ($analysisType === 'dead_stock') {
            // Los platos de menú no tienen stock de inventario físico
            $dishesQuery->whereRaw('1 = 0');
        } else {
            $dishesQuery->where('sales.sold_units', '>', 0);
        }

        if (!empty($filtros['search'])) {
            $term = '%' . trim((string) $filtros['search']) . '%';
            $dishesQuery->where(function($q) use ($term) {
                $q->where('dishes.name', 'like', $term)
                  ->orWhere('dishes.id', 'like', $term)
                  ->orWhere('categories.name', 'like', $term);
            });
        }

        // Combinar ambas consultas
        $combinedQuery = $productsQuery->unionAll($dishesQuery);

        return collect($combinedQuery->get());
    }
}
