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
        $query = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id') // Para filtro de proveedor/laboratorio
            ->select(
                'products.id as product_id',
                'products.barcode',
                'products.name as product_name',
                'laboratories.name as laboratory_name', // alias sugerido
                'products.cost as current_cost', // o unit_cost_usd si es moneda fuerte
                'products.price as list_price',
                DB::raw('SUM(order_details.quantity) as total_sold'),
                DB::raw('SUM(order_details.price * order_details.quantity) as total_revenue'), // Precio real cobrado
                // El descuento real sumado en dinero: precio_lista - precio_vendido
                DB::raw('SUM((order_details.price_before_discount - order_details.price) * order_details.quantity) as total_discount_amount')
            )
            ->where('orders.status', 'completed') // Solo ventas concretadas
            ->groupBy(
                'products.id',
                'products.barcode',
                'products.name',
                'laboratories.name',
                'products.cost',
                'products.price'
            );

        // Filtro de Fechas (usa la tabla orders)
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('orders.created_at', [$filters['start_date'] . ' 00:00:00', $filters['end_date'] . ' 23:59:59']);
        }

        // Filtros adicionales si aplica
        if (!empty($filters['laboratory_id'])) {
            $query->where('products.laboratory_id', $filters['laboratory_id']);
        }
        
        if (!empty($filters['group_id'])) {
            $query->join('groups_products', 'products.id', '=', 'groups_products.product_id')
                  ->where('groups_products.group_id', $filters['group_id']);
        }

        return $query;
    }

    /**
     * Vencidos (Merma) de un periodo por SKU
     */
    public function getExpiredProducts(array $filters)
    {
        $query = ExpiredLog::query()
            ->join('product_lots', 'expired_logs.product_lot_id', '=', 'product_lots.id')
            ->select(
                'product_lots.product_id',
                DB::raw('SUM(expired_logs.quantity) as total_expired_qty'),
                DB::raw('SUM(expired_logs.quantity * product_lots.unit_cost) as total_expired_cost')
            )
            ->groupBy('product_lots.product_id');

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('expired_logs.created_at', [$filters['start_date'] . ' 00:00:00', $filters['end_date'] . ' 23:59:59']);
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
