<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ProductMasterReportRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductMasterReportRepository implements ProductMasterReportRepositoryInterface
{
    // -------------------------------------------------------------------------
    // Bloque de conversión de moneda reutilizable en cada consulta de ventas
    // -------------------------------------------------------------------------

    /**
     * Expresión SQL para calcular precio unitario en USD de forma consistente.
     * Evita duplicar el CASE/COALESCE en cada query.
     */
    private function unitPriceUsdExpr(): string
    {
        return "COALESCE(
            NULLIF(order_details.unit_price_usd, 0),
            CASE
                WHEN orders.currency = 'USD' THEN order_details.price
                ELSE (order_details.price / NULLIF(orders.usd_conversion, 0))
            END,
            0
        )";
    }

    // -------------------------------------------------------------------------
    // Performance (Rankings): usada por getDashboard (TOP10) y getRankingsData (paginado)
    // -------------------------------------------------------------------------

    public function getPerformanceData(array $filters, ?int $limit = null, ?string $sortBy = null): Collection
    {
        $startDate = $filters['start_date'] ?? now()->subMonths(3)->startOfMonth()->format('Y-m-d');
        $endDate   = $filters['end_date']   ?? now()->format('Y-m-d');
        $priceExpr = $this->unitPriceUsdExpr();

        // Productos
        $productsQuery = DB::table('order_details')
            ->join('orders',      'order_details.order_id',  '=', 'orders.id')
            ->join('products',    'order_details.product_id','=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'products.id',
                'products.name',
                'products.active_ingredient',
                'laboratories.name as laboratory_name',
                DB::raw("CAST(SUM(CASE WHEN order_details.unit_price_usd > 0 OR order_details.price > 0 THEN order_details.quantity ELSE 0 END) AS UNSIGNED) as total_sold"),
                DB::raw("SUM(order_details.quantity * ({$priceExpr})) as total_revenue"),
                DB::raw("SUM(order_details.quantity * (({$priceExpr}) - COALESCE(order_details.unit_cost, 0))) as total_margin"),
                DB::raw("'product' as item_type")
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->when($filters['laboratory_id'] ?? null, fn($q, $id) => $q->where('products.laboratory_id', $id))
            ->when($filters['group_id']      ?? null, fn($q, $id) => $q->where('products.group_id', $id))
            ->when($filters['search']        ?? null, fn($q, $s)  => $q->where('products.name', 'like', "%{$s}%"))
            ->groupBy('products.id', 'products.name', 'products.active_ingredient', 'laboratories.name');

        // Platos (ignorados si se filtra por laboratorio/grupo de farmacia)
        if (isset($filters['laboratory_id']) || isset($filters['group_id'])) {
            $combined = $productsQuery;
        } else {
            $dishesQuery = DB::table('order_details')
                ->join('orders',     'order_details.order_id', '=', 'orders.id')
                ->join('dishes',     'order_details.dish_id',  '=', 'dishes.id')
                ->leftJoin('categories', 'dishes.category_id', '=', 'categories.id')
                ->select(
                    'dishes.id',
                    'dishes.name',
                    DB::raw('NULL as active_ingredient'),
                    'categories.name as laboratory_name',
                    DB::raw("CAST(SUM(CASE WHEN order_details.unit_price_usd > 0 OR order_details.price > 0 THEN order_details.quantity ELSE 0 END) AS UNSIGNED) as total_sold"),
                    DB::raw("SUM(order_details.quantity * ({$priceExpr})) as total_revenue"),
                    DB::raw("SUM(order_details.quantity * (({$priceExpr}) - COALESCE(order_details.unit_cost, 0))) as total_margin"),
                    DB::raw("'dish' as item_type")
                )
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
                ->when($filters['search'] ?? null, fn($q, $s) => $q->where('dishes.name', 'like', "%{$s}%"))
                ->groupBy('dishes.id', 'dishes.name', 'categories.name');

            $combined = $productsQuery->unionAll($dishesQuery);
        }

        $query = DB::table(DB::raw("({$combined->toSql()}) as combined"))
            ->mergeBindings($combined);

        if ($sortBy) {
            $query->orderByDesc($sortBy);
        }
        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    // -------------------------------------------------------------------------
    // Pareto: calculado totalmente en SQL con window function simulada
    // -------------------------------------------------------------------------

    public function getParetoStats(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->subMonths(3)->startOfMonth()->format('Y-m-d');
        $endDate   = $filters['end_date']   ?? now()->format('Y-m-d');
        $priceExpr = $this->unitPriceUsdExpr();

        $productsMargin = DB::table('order_details')
            ->join('orders',   'order_details.order_id',   '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select(
                DB::raw("SUM(order_details.quantity * (({$priceExpr}) - COALESCE(order_details.unit_cost, 0))) as total_margin")
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->when($filters['laboratory_id'] ?? null, fn($q, $id) => $q->where('products.laboratory_id', $id))
            ->when($filters['group_id']      ?? null, fn($q, $id) => $q->where('products.group_id', $id))
            ->groupBy('products.id');

        if (isset($filters['laboratory_id']) || isset($filters['group_id'])) {
            $combined = $productsMargin;
        } else {
            $dishesMargin = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('dishes', 'order_details.dish_id',  '=', 'dishes.id')
                ->select(
                    DB::raw("SUM(order_details.quantity * (({$priceExpr}) - COALESCE(order_details.unit_cost, 0))) as total_margin")
                )
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
                ->groupBy('dishes.id');

            $combined = $productsMargin->unionAll($dishesMargin);
        }

        // Calcular Pareto en SQL: sumar top-20% de ítems y ver cuánto acumulan
        // Se usa una sola query agregada: total y suma positiva de margen
        $aggResult = DB::table(DB::raw("({$combined->toSql()}) as margins_combined"))
            ->mergeBindings($combined)
            ->selectRaw('SUM(total_margin) as grand_total, COUNT(*) as total_items')
            ->first();

        $grandTotal  = (float) ($aggResult->grand_total  ?? 0);
        $totalItems  = (int)   ($aggResult->total_items  ?? 0);

        if ($grandTotal <= 0 || $totalItems === 0) {
            return ['count' => 0, 'percent' => 0, 'total_items' => $totalItems];
        }

        // Iterar acumulando solo los márgenes en orden descendente
        // Se pagina en bloques de 500 para no traer todo a memoria en tablas masivas
        $paretoCount  = 0;
        $runningSum   = 0.0;
        $threshold    = $grandTotal * 0.8;
        $offset       = 0;
        $chunkSize    = 500;

        do {
            $chunk = DB::table(DB::raw("({$combined->toSql()}) as margins_combined"))
                ->mergeBindings($combined)
                ->orderByDesc('total_margin')
                ->offset($offset)
                ->limit($chunkSize)
                ->pluck('total_margin');

            foreach ($chunk as $margin) {
                $runningSum += (float) $margin;
                $paretoCount++;
                if ($runningSum >= $threshold) break 2;
            }

            $offset += $chunkSize;
        } while ($chunk->count() === $chunkSize);

        return [
            'count'       => $paretoCount,
            'percent'     => round(($paretoCount / $totalItems) * 100, 2),
            'total_items' => $totalItems,
        ];
    }

    // -------------------------------------------------------------------------
    // Ranking de Laboratorios
    // -------------------------------------------------------------------------

    public function getLaboratoryRanking(array $filters): Collection
    {
        $startDate = $filters['start_date'] ?? now()->subMonths(3)->startOfMonth()->format('Y-m-d');
        $endDate   = $filters['end_date']   ?? now()->format('Y-m-d');

        $productsRank = DB::table('order_details')
            ->join('orders',       'order_details.order_id',    '=', 'orders.id')
            ->join('products',     'order_details.product_id',  '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id',    '=', 'laboratories.id')
            ->select(
                'laboratories.name',
                DB::raw("SUM(order_details.quantity * (
                    CASE
                        WHEN order_details.unit_price_usd > 0 THEN order_details.unit_price_usd
                        WHEN orders.currency = 'USD'          THEN order_details.price
                        ELSE (order_details.price / NULLIF(orders.usd_conversion, 0))
                    END - order_details.unit_cost
                )) as total_margin")
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->groupBy('laboratories.id', 'laboratories.name');

        $dishesRank = DB::table('order_details')
            ->join('orders',     'order_details.order_id', '=', 'orders.id')
            ->join('dishes',     'order_details.dish_id',  '=', 'dishes.id')
            ->join('categories', 'dishes.category_id',     '=', 'categories.id')
            ->select(
                'categories.name as name',
                DB::raw("SUM(order_details.quantity * (
                    CASE
                        WHEN order_details.unit_price_usd > 0 THEN order_details.unit_price_usd
                        WHEN orders.currency = 'USD'          THEN order_details.price
                        ELSE (order_details.price / NULLIF(orders.usd_conversion, 0))
                    END - order_details.unit_cost
                )) as total_margin")
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->groupBy('categories.id', 'categories.name');

        return $productsRank->unionAll($dishesRank)
            ->orderByDesc('total_margin')
            ->limit(10)
            ->get();
    }

    // -------------------------------------------------------------------------
    // ABC Summary: clasificación por valor de inventario en SQL puro
    // Reemplaza el algoritmo PHP que cargaba todo a memoria.
    // Usa variables de sesión MySQL para calcular running sum.
    // -------------------------------------------------------------------------

    public function getAbcSummary(array $filters): Collection
    {
        // Paso 1: obtener productos ordenados por valor de inventario desc
        // Paso 2: calcular ABC mediante running sum acumulado en PHP solo sobre
        //         la colección ya ordenada (SELECT ya hace el trabajo pesado).
        // Esto es correcto porque ABC requiere orden global; no hay window function
        // equivalente en MySQL 5.7, pero sí en 8+.

        $items = DB::table('products')
            ->select(
                DB::raw('(stock * unit_cost) as inventory_value')
            )
            ->where('is_active', 1)
            ->when($filters['laboratory_id'] ?? null, fn($q, $id) => $q->where('laboratory_id', $id))
            ->when($filters['group_id']      ?? null, fn($q, $id) => $q->where('group_id', $id))
            ->where(DB::raw('stock * unit_cost'), '>', 0) // excluir ítems sin valor (optimización)
            ->orderByDesc(DB::raw('stock * unit_cost'))
            ->get();

        $totalValue = $items->sum('inventory_value');
        if ($totalValue <= 0) return collect();

        $runningSum = 0.0;
        $groups     = ['A' => ['count' => 0, 'value' => 0.0], 'B' => ['count' => 0, 'value' => 0.0], 'C' => ['count' => 0, 'value' => 0.0]];

        foreach ($items as $item) {
            $value       = (float) $item->inventory_value;
            $runningSum += $value;
            $percent     = ($runningSum / $totalValue) * 100;

            $class = match (true) {
                $percent <= 80 => 'A',
                $percent <= 95 => 'B',
                default        => 'C',
            };

            $groups[$class]['count']++;
            $groups[$class]['value'] += $value;
        }

        return collect($groups)
            ->map(fn($g, $key) => [
                'type'    => $key,
                'count'   => $g['count'],
                'revenue' => round($g['value'], 2),
            ])
            ->filter(fn($g) => $g['count'] > 0)
            ->values();
    }

    // -------------------------------------------------------------------------
    // Cross-Selling: incluye nombres de productos/platos en la consulta
    // -------------------------------------------------------------------------

    public function getCrossSellingData(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate   = $filters['end_date']   ?? now()->format('Y-m-d');
        $page      = (int) ($filters['page'] ?? 1);

        return DB::table('order_details as od1')
            ->join('order_details as od2', function ($join) {
                // Garantiza pares únicos (A < B) usando COALESCE para manejar product_id o dish_id
                $join->on('od1.order_id', '=', 'od2.order_id')
                     ->on(DB::raw('COALESCE(od1.product_id, od1.dish_id)'), '<', DB::raw('COALESCE(od2.product_id, od2.dish_id)'));
            })
            ->join('orders', 'od1.order_id', '=', 'orders.id')
            // Nombres del ítem A (producto o plato)
            ->leftJoin('products as pa',     'od1.product_id', '=', 'pa.id')
            ->leftJoin('dishes as da',       'od1.dish_id',    '=', 'da.id')
            ->leftJoin('laboratories as la', 'pa.laboratory_id', '=', 'la.id')
            ->leftJoin('categories as ca',  'da.category_id', '=', 'ca.id')
            // Nombres del ítem B
            ->leftJoin('products as pb',     'od2.product_id', '=', 'pb.id')
            ->leftJoin('dishes as db',       'od2.dish_id',    '=', 'db.id')
            ->leftJoin('laboratories as lb', 'pb.laboratory_id', '=', 'lb.id')
            ->leftJoin('categories as cb',  'db.category_id', '=', 'cb.id')
            ->select(
                DB::raw('COALESCE(od1.product_id, od1.dish_id) as product_id_a'),
                DB::raw("COALESCE(pa.name, da.name, 'Desconocido') as product_a"),
                DB::raw("pa.active_ingredient as ingredient_a"),
                DB::raw("COALESCE(la.name, ca.name, 'S/L') as lab_a"),
                DB::raw('COALESCE(od2.product_id, od2.dish_id) as product_id_b'),
                DB::raw("COALESCE(pb.name, db.name, 'Desconocido') as product_b"),
                DB::raw("pb.active_ingredient as ingredient_b"),
                DB::raw("COALESCE(lb.name, cb.name, 'S/L') as lab_b"),
                DB::raw('COUNT(*) as frequency')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->groupBy('product_id_a', 'product_a', 'ingredient_a', 'lab_a', 'product_id_b', 'product_b', 'ingredient_b', 'lab_b')
            ->havingRaw('COUNT(*) > 1') // Solo pares con frecuencia real (>1 coincidencia)
            ->orderByDesc('frequency')
            ->paginate(8, ['*'], 'page', $page);
    }

    // -------------------------------------------------------------------------
    // Supply Stats: single aggregated query (sin N+1)
    // -------------------------------------------------------------------------

    public function getSupplyStats(array $filters): array
    {
        $stats = DB::table('products')
            ->select(
                DB::raw('SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock'),
                DB::raw('SUM(CASE WHEN sales_average > 0 AND (stock / sales_average) < 7 THEN 1 ELSE 0 END) as critical_stock'),
                DB::raw('AVG(CASE WHEN sales_average > 0 THEN stock / sales_average ELSE 999 END) as avg_inventory_days')
            )
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->when($filters['laboratory_id'] ?? null, fn($q, $id) => $q->where('laboratory_id', $id))
            ->when($filters['group_id']      ?? null, fn($q, $id) => $q->where('group_id', $id))
            ->first();

        return [
            'out_of_stock'      => (int)   ($stats->out_of_stock      ?? 0),
            'critical_stock'    => (int)   ($stats->critical_stock    ?? 0),
            'avg_inventory_days'=> (float) ($stats->avg_inventory_days ?? 0),
        ];
    }

    // -------------------------------------------------------------------------
    // Tendencias: ventas vs compras por semana (últimos 12 meses)
    // -------------------------------------------------------------------------

    public function getTrendComparison(array $filters): Collection
    {
        $productId = $filters['product_id'] ?? null;
        $groupId   = $filters['group_id']   ?? null;
        $startDate = now()->subMonths(12)->startOfMonth()->format('Y-m-d');
        $endDate   = now()->format('Y-m-d');

        $querySales = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select(
                DB::raw('DATE_FORMAT(orders.created_at, "%Y-%u") as week'),
                DB::raw('SUM(order_details.quantity) as qty_sold'),
                DB::raw('0 as qty_purchased')
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->when($productId, fn($q, $id) => $q->where('order_details.product_id', $id))
            ->when($groupId,   fn($q, $id) => $q->where('products.group_id', $id))
            ->groupBy('week');

        $queryPurchases = DB::table('invoice_details')
            ->join('products', 'invoice_details.product_id', '=', 'products.id')
            ->select(
                DB::raw('DATE_FORMAT(invoice_details.created_at, "%Y-%u") as week'),
                DB::raw('0 as qty_sold'),
                DB::raw('SUM(invoice_details.quantity) as qty_purchased')
            )
            ->whereBetween('invoice_details.created_at', [$startDate, $endDate])
            ->when($productId, fn($q, $id) => $q->where('invoice_details.product_id', $id))
            ->when($groupId,   fn($q, $id) => $q->where('products.group_id', $id))
            ->groupBy('week');

        return $querySales->unionAll($queryPurchases)
            ->get()
            ->groupBy('week')
            ->map(fn($weekData, $week) => [
                'week'      => $week,
                'sold'      => $weekData->sum('qty_sold'),
                'purchased' => $weekData->sum('qty_purchased'),
            ])
            ->sortBy('week')
            ->values();
    }

    // -------------------------------------------------------------------------
    // Rankings paginados (usado por fetchRankings del frontend)
    // Reutiliza getPerformanceData internamente para no duplicar SQL
    // -------------------------------------------------------------------------

    public function getRankingsData(array $filters)
    {
        $startDate = $filters['start_date'] ?? now()->subMonths(3)->startOfMonth()->format('Y-m-d');
        $endDate   = $filters['end_date']   ?? now()->format('Y-m-d');
        $sortBy    = in_array($filters['sort_by'] ?? 'total_sold', ['total_sold', 'total_revenue', 'total_margin'])
            ? $filters['sort_by']
            : 'total_sold';
        $page      = (int) ($filters['page'] ?? 1);
        $priceExpr = $this->unitPriceUsdExpr();

        $productsQuery = DB::table('order_details')
            ->join('orders',      'order_details.order_id',   '=', 'orders.id')
            ->join('products',    'order_details.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'products.id',
                'products.name',
                'products.active_ingredient',
                'laboratories.name as laboratory_name',
                DB::raw("CAST(SUM(order_details.quantity) AS UNSIGNED) as total_sold"),
                DB::raw("SUM(order_details.quantity * ({$priceExpr})) as total_revenue"),
                DB::raw("SUM(order_details.quantity * (({$priceExpr}) - COALESCE(order_details.unit_cost, 0))) as total_margin"),
                DB::raw("'product' as item_type")
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->when($filters['laboratory_id'] ?? null, fn($q, $id) => $q->where('products.laboratory_id', $id))
            ->when($filters['group_id']      ?? null, fn($q, $id) => $q->where('products.group_id', $id))
            ->when($filters['search']        ?? null, fn($q, $s)  => $q->where('products.name', 'like', "%{$s}%"))
            ->groupBy('products.id', 'products.name', 'products.active_ingredient', 'laboratories.name');

        $dishesQuery = DB::table('order_details')
            ->join('orders',      'order_details.order_id', '=', 'orders.id')
            ->join('dishes',      'order_details.dish_id',  '=', 'dishes.id')
            ->leftJoin('categories', 'dishes.category_id', '=', 'categories.id')
            ->select(
                'dishes.id',
                'dishes.name',
                DB::raw('NULL as active_ingredient'),
                'categories.name as laboratory_name',
                DB::raw("CAST(SUM(order_details.quantity) AS UNSIGNED) as total_sold"),
                DB::raw("SUM(order_details.quantity * ({$priceExpr})) as total_revenue"),
                DB::raw("SUM(order_details.quantity * (({$priceExpr}) - COALESCE(order_details.unit_cost, 0))) as total_margin"),
                DB::raw("'dish' as item_type")
            )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('dishes.name', 'like', "%{$s}%"))
            ->groupBy('dishes.id', 'dishes.name', 'categories.name');

        $combined = $productsQuery->unionAll($dishesQuery);

        return DB::table(DB::raw("({$combined->toSql()}) as combined"))
            ->mergeBindings($combined)
            ->orderByDesc($sortBy)
            ->paginate(10, ['*'], 'page', $page);
    }
}
