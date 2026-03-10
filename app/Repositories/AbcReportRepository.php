<?php

namespace App\Repositories;

use App\Contracts\Repositories\AbcReportRepositoryInterface;
use App\Models\Product;
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

        // Subconsulta para calcular la rotación (Desviación Típica de ventas diarias por producto)
        $dailySalesQuery = DB::table('order_details')
            ->select(
                'order_details.product_id',
                DB::raw('DATE(orders.order_date) as order_date_only'),
                DB::raw('SUM(order_details.quantity) as daily_quantity')
            )
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.order_date', [$startDate, $endDate])
            ->groupBy('order_details.product_id', 'order_date_only');

        $varianceSubquery = DB::table(DB::raw("({$dailySalesQuery->toSql()}) as daily_sales"))
            ->mergeBindings($dailySalesQuery)
            ->select(
                'daily_sales.product_id',
                DB::raw('STDDEV(daily_sales.daily_quantity) as std_dev_sales'),
                DB::raw('AVG(daily_sales.daily_quantity) as avg_daily_sales')
            )
            ->groupBy('daily_sales.product_id');

        // Consulta Principal: Obtener el resumen de ventas (Ventas Totales, Costos, Margen) por producto
        $query = Product::query()
            ->select(
                'products.id',
                'products.name as product_name',
                'categories.name as category_name',
                'laboratories.name as laboratory_name',
                'products.stock as current_stock',
                'products.unit_cost as last_cost', // Asumido desde products.unit_cost o product_lots dependiendo del negocio
                // Agregados de Ventas
                DB::raw('COALESCE(SUM(order_details.quantity), 0) as sold_units'),
                DB::raw('COALESCE(SUM(order_details.quantity * order_details.price), 0) as total_sales'),
                DB::raw('COALESCE(SUM(order_details.quantity * order_details.unit_cost), 0) as total_cost'),
                // Variables de cálculo para XYZ
                DB::raw('COALESCE(variance.std_dev_sales, 0) as std_dev_sales'),
                DB::raw('COALESCE(variance.avg_daily_sales, 0) as avg_daily_sales')
            )
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoin('order_details', 'products.id', '=', 'order_details.product_id')
            ->leftJoin('orders', function ($join) use ($startDate, $endDate) {
                $join->on('order_details.order_id', '=', 'orders.id')
                     ->where('orders.status', 'Completed')
                     ->whereBetween('orders.order_date', [$startDate, $endDate]);
            })
            ->leftJoinSub($varianceSubquery, 'variance', function($join) {
                $join->on('products.id', '=', 'variance.product_id');
            })
            // Solo traer productos que hayan tenido alguna venta o tengan stock para ser analizados
            ->havingRaw('sold_units > 0 OR current_stock > 0')
            ->groupBy(
                'products.id',
                'products.name',
                'categories.name',
                'laboratories.name',
                'products.stock',
                'products.unit_cost',
                'variance.std_dev_sales',
                'variance.avg_daily_sales'
            );

        // Aplicar Filtros adicionales
        if (!empty($filtros['category_id'])) {
            $query->whereIn('products.category_id', (array) $filtros['category_id']);
        }
        
        if (!empty($filtros['laboratory_id'])) {
            $query->whereIn('products.laboratory_id', (array) $filtros['laboratory_id']);
        }

        return $query->get();
    }
}
