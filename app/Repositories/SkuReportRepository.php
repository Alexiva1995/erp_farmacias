<?php

namespace App\Repositories;

use App\Contracts\Repositories\SkuReportRepositoryInterface;
use App\Models\OrderDetail;
use App\Models\ExpiredLog;
use App\Models\InvoiceDetail;
use App\Models\ReturnEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SkuReportRepository implements SkuReportRepositoryInterface
{
    /**
     * Obtiene el query base para las ventas (OrderDetail).
     */
    public function getBaseQuery(array $filters): Builder
    {
        // Filtro de Fechas (usa la tabla orders) — Se restringe estrictamente a partir de Abril 2026
        $minDate = '2026-04-01 00:00:00';
        $startDate = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : $minDate;
        if ($startDate < $minDate) $startDate = $minDate;
        
        $endDate = !empty($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : null;

        // Query Base para Productos
        $productsQuery = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'products.id as product_id',
                'products.barcode',
                'products.name as product_name',
                'laboratories.name as laboratory_name',
                'products.unit_cost as current_cost',
                'products.sale_price as list_price',
                DB::raw('SUM(order_details.quantity) as total_sold'),
                DB::raw('SUM(order_details.unit_cost * order_details.quantity) as total_historical_cost'),
                DB::raw('SUM(order_details.quantity * CASE 
                    WHEN order_details.unit_price_usd > 0 THEN order_details.unit_price_usd 
                    WHEN orders.currency = \'USD\' THEN order_details.price 
                    ELSE (order_details.price / NULLIF(orders.usd_conversion, 0)) 
                END) as total_revenue'),
                DB::raw('SUM(order_details.quantity * CASE
                    WHEN order_details.price_before_discount IS NOT NULL AND orders.currency = \'USD\' THEN (order_details.price_before_discount - order_details.price)
                    WHEN order_details.price_before_discount IS NOT NULL AND orders.currency != \'USD\' THEN ((order_details.price_before_discount - order_details.price) / NULLIF(orders.usd_conversion, 0))
                    ELSE 0
                END) as total_discount_amount'),
                DB::raw("'product' as item_type")
            )
            ->where('orders.status', 'completed')
            ->groupBy(
                'products.id',
                'products.barcode',
                'products.name',
                'laboratories.name',
                'products.unit_cost',
                'products.sale_price'
            );

        // Query Base para Platos (Dishes)
        $dishesQuery = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('dishes', 'order_details.dish_id', '=', 'dishes.id')
            ->leftJoin('categories', 'dishes.category_id', '=', 'categories.id')
            ->select(
                'dishes.id as product_id',
                DB::raw('NULL as barcode'),
                'dishes.name as product_name',
                'categories.name as laboratory_name', // Usamos categoría como laboratorio para platos
                'dishes.cost_price as current_cost',
                'dishes.designated_price as list_price',
                DB::raw('SUM(order_details.quantity) as total_sold'),
                DB::raw('SUM(order_details.unit_cost * order_details.quantity) as total_historical_cost'),
                DB::raw('SUM(order_details.quantity * CASE 
                    WHEN order_details.unit_price_usd > 0 THEN order_details.unit_price_usd 
                    WHEN orders.currency = \'USD\' THEN order_details.price 
                    ELSE (order_details.price / NULLIF(orders.usd_conversion, 0)) 
                END) as total_revenue'),
                DB::raw('SUM(order_details.quantity * CASE
                    WHEN order_details.price_before_discount IS NOT NULL AND orders.currency = \'USD\' THEN (order_details.price_before_discount - order_details.price)
                    WHEN order_details.price_before_discount IS NOT NULL AND orders.currency != \'USD\' THEN ((order_details.price_before_discount - order_details.price) / NULLIF(orders.usd_conversion, 0))
                    ELSE 0
                END) as total_discount_amount'),
                DB::raw("'dish' as item_type")
            )
            ->where('orders.status', 'completed')
            ->groupBy(
                'dishes.id',
                'dishes.name',
                'categories.name',
                'dishes.cost_price',
                'dishes.designated_price'
            );

        // Aplicar Filtros comunes
        foreach ([$productsQuery, $dishesQuery] as $query) {
            if ($endDate) {
                $query->whereBetween('orders.created_at', [$startDate, $endDate]);
            } else {
                $query->where('orders.created_at', '>=', $startDate);
            }

            if (!empty($filters['search'])) {
                $search = $filters['search'];
                // Para platos el barcode es null, así que solo buscamos por nombre
                $query->where(function($q) use ($search) {
                    $q->where(DB::raw('COALESCE(products.name, dishes.name)'), 'LIKE', "%{$search}%")
                      ->orWhere('products.barcode', 'LIKE', "%{$search}%");
                });
            }
        }

        // Filtros específicos de productos
        if (!empty($filters['laboratory_id'])) {
            $productsQuery->where('products.laboratory_id', $filters['laboratory_id']);
            // Si filtramos por laboratorio, platos no deberían aparecer a menos que mapeemos categorías?
            // Por ahora, si hay filtro de lab, vaciamos la query de platos para consistencia
            $dishesQuery->whereRaw('1 = 0');
        }
        
        if (!empty($filters['group_id'])) {
            $productsQuery->join('groups_products', 'products.id', '=', 'groups_products.product_id')
                          ->where('groups_products.group_id', $filters['group_id']);
            $dishesQuery->whereRaw('1 = 0');
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $productsQuery->where('products.is_active', $filters['is_active']);
            $dishesQuery->where('dishes.status', $filters['is_active'] ? 1 : 0);
        }

        return $productsQuery->union($dishesQuery);
    }

    /**
     * Vencidos (Merma) de un periodo por SKU
     */
    public function getExpiredProducts(array $filters)
    {
        $query = ExpiredLog::query()
            ->select(
                'product_id',
                DB::raw('SUM(expired_quantity) as total_expired_qty'),
                DB::raw('SUM(total_lost_value) as total_expired_cost')
            )
            ->groupBy('product_id');

        $minDate = '2026-04-01 00:00:00';
        $startDate = !empty($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : $minDate;
        
        if ($startDate < $minDate) {
            $startDate = $minDate;
        }

        if (!empty($filters['end_date'])) {
            $endDate = $filters['end_date'] . ' 23:59:59';
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $query->where('created_at', '>=', $startDate);
        }

        return $query->get()->keyBy('product_id');
    }

    /**
     * Devoluciones (ej. Invoices no recuperadas o Return Entries)
     * Por simplicidad sumaremos los que están en la tabla returns
     */
    public function getReturnedProducts(array $filters)
    {
        // En tu esquema 'returns' aparentemente usa ReturnEntry o la lógica interna
        // Si no tienes una tabla explícita de merma de devoluciones, este es el Query Base.
        // Asumo el uso de 'InvoiceReturn' o 'ReturnEntry' si rastrea productos (habrá que ajustar a tu DB)
        
        // Hacemos el ejemplo con una estructura general de devoluciones. Si no aplica a SKU directo, devuelve array vacio.
        return collect([]); 
    }
}
