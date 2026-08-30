<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\MarketOpportunityRepositoryInterface;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Clase MarketOpportunityRepository
 * 
 * Se encarga de la lógica de base de datos para identificar oportunidades de mercado
 * basadas en la comparación de precios de proveedores contra costos históricos y actuales.
 */
class MarketOpportunityRepository implements MarketOpportunityRepositoryInterface
{
    /**
     * Construye la consulta base para las oportunidades de mercado.
     * 
     * Compara product_suppliers.unit_cost_usd con el menor valor entre
     * el costo histórico de lotes (12 meses) y el costo actual de inventario.
     *
     * @param array $filtros
     * @return Builder
     */
    protected function builderMarketOpportunities(array $filtros): Builder
    {
        $doceMesesAtras = now()->subMonths(12)->toDateString();
        $withDiscount = filter_var($filtros['withDiscount'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hideRedundant = filter_var($filtros['hideRedundant'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $hideDuplicates = filter_var($filtros['hideDuplicates'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $excludeSupplierIds = !empty($filtros['excludeSupplierIds']) 
            ? (is_array($filtros['excludeSupplierIds']) ? $filtros['excludeSupplierIds'] : [$filtros['excludeSupplierIds']])
            : [];

        // Expresión para el precio de oferta efectivo (Full vs Descuento con fallback)
        $offerPriceExpression = $withDiscount 
            ? 'COALESCE(NULLIF(product_suppliers.unit_cost_usd_with_discount, 0), product_suppliers.unit_cost_usd)'
            : 'product_suppliers.unit_cost_usd';

        $lapsoStr = $filtros['lapso_de_tiempo'] ?? '3 month';
        $tresMesesAtras = now()->modify('-' . $lapsoStr)->toDateTimeString();
        $hoy = now()->toDateTimeString();

        // Subconsultas de agrupación optimizadas para evitar N+1
        $currentDateExpr = DB::connection()->getDriverName() === 'sqlite' ? "DATE('now')" : 'CURDATE()';

        $stockAggregated = DB::table('product_lots')
            ->select('product_id', DB::raw('COALESCE(SUM(quantity), 0) as lote_quantity'))
            ->where('quantity', '>', 0)
            ->groupBy('product_id');

        $autoOrderAggregated = DB::table('auto_order_details as aod')
            ->join('auto_orders as ao', 'ao.id', '=', 'aod.order_id')
            ->select('aod.product_id', DB::raw('COALESCE(SUM(aod.quantity), 0) as totalQuantityInAutoOrder'))
            ->whereIn('ao.status', [0, 1])
            ->where('aod.status', 0)
            ->whereNull('ao.deleted_at')
            ->whereNull('aod.deleted_at')
            ->groupBy('aod.product_id');

        $salesAggregated = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->select('order_details.product_id', DB::raw('COALESCE(SUM(order_details.quantity), 0) as total_sold_completed'))
            ->whereBetween('orders.created_at', [$tresMesesAtras, $hoy])
            ->where('orders.status', 'Completed')
            ->groupBy('order_details.product_id');

        // Subconsulta para el costo mínimo y máximo histórico por producto en los lotes (últimos 12 meses)
        $historicCostSubquery = DB::table('product_lots')
            ->select('product_id', DB::raw('MIN(unit_cost) as min_historic_cost'), DB::raw('MAX(unit_cost) as max_historic_cost'))
            ->whereDate('created_at', '>=', $doceMesesAtras)
            ->groupBy('product_id');

        /**
         * Subconsulta base para clasificar las ofertas por producto.
         * Selecciona todas las ofertas que son mejores que nuestro costo de inventario.
         * La numeración (row_num) se usa para la deduplicación (solo mejor oferta).
         */
        $latestIdsQuery = DB::table('product_suppliers')
            ->select(DB::raw('MAX(id)'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('product_id', 'supplier_id');

        $sub = ProductSupplier::query()
            ->whereIn('product_suppliers.id', $latestIdsQuery)
            ->select(
                'product_suppliers.*',
                'products.name as product_name_inventory',
                'products.active_ingredient as active_ingredient_inventory',
                'products.unit_cost as inventory_unit_cost',
                'products.laboratory_id',
                'laboratories.name as laboratory_name',
                'suppliers.name as supplier_name',
                DB::raw($offerPriceExpression . ' as effective_offer_price'),
                DB::raw('COALESCE(sales.total_sold_completed, 0) as total_sold_completed'),
                DB::raw('COALESCE(stock.lote_quantity, 0) as lote_quantity'),
                DB::raw("CASE 
                    WHEN '$lapsoStr' = '7 days' THEN products.sales_average / 4
                    WHEN '$lapsoStr' = '15 days' THEN products.sales_average / 2
                    WHEN '$lapsoStr' = '1 month' THEN products.sales_average
                    WHEN '$lapsoStr' = '3 month' THEN products.sales_average * 3
                    WHEN '$lapsoStr' = '6 month' THEN products.sales_average * 6
                    WHEN '$lapsoStr' = '12 month' THEN products.sales_average * 12
                    WHEN '$lapsoStr' = '1 year' THEN products.sales_average * 12
                    ELSE products.sales_average * 3
                END as promedio_calculado"),
                DB::raw('COALESCE(ao.totalQuantityInAutoOrder, 0) as totalQuantityInAutoOrder')
            )
            ->when($hideDuplicates, function ($q) use ($offerPriceExpression) {
                $q->addSelect(DB::raw('ROW_NUMBER() OVER(PARTITION BY product_suppliers.product_id ORDER BY ' . $offerPriceExpression . ' ASC) as row_num'));
            })
            ->join('products', 'product_suppliers.product_id', '=', 'products.id')
            ->join('suppliers', 'product_suppliers.supplier_id', '=', 'suppliers.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoinSub($stockAggregated, 'stock', 'products.id', '=', 'stock.product_id')
            ->leftJoinSub($autoOrderAggregated, 'ao', 'products.id', '=', 'ao.product_id')
            ->leftJoinSub($salesAggregated, 'sales', 'products.id', '=', 'sales.product_id')
            ->whereRaw($offerPriceExpression . ' > 0')
            ->where('products.unit_cost', '>', 0)
            ->whereRaw($offerPriceExpression . ' < products.unit_cost')
            ->when($hideRedundant, function ($q) {
                // En este sistema, "Redundante" se identifica con la columna 'is_scarce'
                $q->where('products.is_scarce', 0);
            })
            ->when(isset($filtros['is_colombia']), function ($q) use ($filtros) {
                $isCol = filter_var($filtros['is_colombia'], FILTER_VALIDATE_BOOLEAN);
                $q->where('products.is_colombian_origin', $isCol ? 1 : 0);
            })
            ->when(!empty($excludeSupplierIds), function ($q) use ($excludeSupplierIds) {
                $q->whereNotIn('product_suppliers.supplier_id', $excludeSupplierIds);
            });

        // Definición de DEMANDA según tipo de filtración para el SELECT final y filtrado
        $tipoFiltracion = $filtros['tipo_filtracion'] ?? 'combinado';
        $demandaSql = match($tipoFiltracion) {
            'sales'      => 'sub.total_sold_completed',
            'average'    => 'sub.promedio_calculado',
            'combinado'  => "(CASE WHEN sub.total_sold_completed > 0 THEN ((sub.promedio_calculado + sub.total_sold_completed) / 2) ELSE sub.promedio_calculado END)",
            default      => "(CASE WHEN sub.total_sold_completed > 0 THEN ((sub.promedio_calculado + sub.total_sold_completed) / 2) ELSE sub.promedio_calculado END)",
        };

        $query = ProductSupplier::query()
            ->fromSub($sub, 'sub')
            ->select(
                'sub.id',
                'sub.product_id',
                'sub.supplier_id',
                'sub.effective_offer_price as unit_cost_usd',
                'sub.name as product_name_supplier',
                'sub.product_name_inventory',
                'sub.active_ingredient_inventory',
                'sub.inventory_unit_cost',
                'sub.laboratory_name',
                'sub.supplier_name',
                'sub.total_sold_completed',
                'sub.lote_quantity',
                'sub.promedio_calculado',
                'sub.totalQuantityInAutoOrder',
                // Solicitar = Demanda - Stock - AutoOrder
                DB::raw(DB::connection()->getDriverName() === 'sqlite' 
                    ? "CASE 
                        WHEN ($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) > 0 
                        THEN CAST(($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) + 0.999999 AS INTEGER)
                        ELSE CAST(($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) AS INTEGER)
                       END as solicitar"
                    : "CASE 
                        WHEN ($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) > 0 
                        THEN CEIL($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder)
                        ELSE FLOOR($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder)
                       END as solicitar"),
                // Mínimo histórico con fallback al costo de inventario
                DB::raw('COALESCE(historic.min_historic_cost, sub.inventory_unit_cost) as effective_min_cost'),
                // Máximo histórico con fallback al costo de inventario
                DB::raw('COALESCE(historic.max_historic_cost, sub.inventory_unit_cost) as effective_max_cost'),
                // Porcentaje de ahorro real: ((Costo - Oferta) / Costo) * 100
                DB::raw(DB::connection()->getDriverName() === 'sqlite'
                    ? 'CAST(((sub.inventory_unit_cost - sub.effective_offer_price) / sub.inventory_unit_cost) * 100 AS INTEGER) as saving_percentage'
                    : 'FLOOR(((sub.inventory_unit_cost - sub.effective_offer_price) / sub.inventory_unit_cost) * 100) as saving_percentage')
            )
            ->leftJoinSub($historicCostSubquery, 'historic', function ($join) {
                $join->on('sub.product_id', '=', 'historic.product_id');
            })
            ->when($hideDuplicates, function ($q) {
                $q->where('sub.row_num', 1);
            });

        // Filtro de Stock (Fallas/Exceso)
        $stockFilter = $filtros['stock'] ?? 'all';
        if ($stockFilter === 'fallas') {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $query->whereRaw("(CASE 
                    WHEN ($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) > 0 
                    THEN CAST(($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) + 0.999999 AS INTEGER)
                    ELSE CAST(($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) AS INTEGER)
                END) > 0");
            } else {
                $query->whereRaw("(CASE 
                    WHEN ($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) > 0 
                    THEN CEIL($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder)
                    ELSE FLOOR($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder)
                END) > 0");
            }
        } elseif ($stockFilter === 'exceso') {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $query->whereRaw("(CASE 
                    WHEN ($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) > 0 
                    THEN CAST(($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) + 0.999999 AS INTEGER)
                    ELSE CAST(($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) AS INTEGER)
                END) < 0");
            } else {
                $query->whereRaw("(CASE 
                    WHEN ($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder) > 0 
                    THEN CEIL($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder)
                    ELSE FLOOR($demandaSql - sub.lote_quantity - sub.totalQuantityInAutoOrder)
                END) < 0");
            }
        }

        // Aplicación de filtros de búsqueda
        if (!empty($filtros['q'])) {
            $query->where(function ($q) use ($filtros) {
                $searchTerm = '%' . $filtros['q'] . '%';
                // Usamos los nombres de columna tal cual vienen en la subconsulta 'sub'
                $q->where('sub.name', 'like', $searchTerm)
                  ->orWhere('sub.barcode_match', 'like', $searchTerm)
                  ->orWhere('sub.product_name_inventory', 'like', $searchTerm);
            });
        }

        // Filtro por laboratorio
        if (!empty($filtros['laboratoryId'])) {
            $labIds = is_array($filtros['laboratoryId']) ? $filtros['laboratoryId'] : [$filtros['laboratoryId']];
            $query->whereIn('sub.laboratory_id', $labIds);
        }

        // Filtro por producto específico
        if (!empty($filtros['productId'])) {
            $productIds = is_array($filtros['productId']) ? $filtros['productId'] : [$filtros['productId']];
            $query->whereIn('sub.product_id', $productIds);
        }

        // Ordenamiento (Por defecto mayor porcentaje de ahorro)
        $sortBy = $filtros['sortBy'] ?? 'saving_percentage';
        $orderBy = $filtros['orderBy'] ?? 'desc';

        if ($sortBy === 'price') {
            $sortBy = 'unit_cost_usd';
        }

        return $query->orderBy($sortBy, $orderBy);
    }

    /**
     * Obtener oportunidades de mercado paginadas.
     *
     * @param array $filtros
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedOpportunities(array $filtros, int $perPage = 10): LengthAwarePaginator
    {
        return $this->builderMarketOpportunities($filtros)->paginate($perPage);
    }

    /**
     * Obtener todas las oportunidades de mercado sin paginar.
     *
     * @param array $filtros
     * @return Collection
     */
    public function getAllOpportunities(array $filtros): Collection
    {
        return $this->builderMarketOpportunities($filtros)->get();
    }
}
