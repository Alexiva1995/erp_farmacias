<?php

namespace App\Repositories;

use App\Contracts\Repositories\IndividualOfferRepositoryInterface;
use App\Models\IndividualOffer;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IndividualOfferRepository implements IndividualOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = IndividualOffer::query()
            ->with(['product:id,name,active_ingredient,sale_price,laboratory_id', 'product.laboratory:id,name']);

        // Subconsulta optimizada en SQL puro / Eloquent sin N+1 para calcular la suma de cantidad vendida
        $query->addSelect([
            'sales_count' => DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereColumn('order_details.product_id', 'individual_offers.product_id')
                ->where('orders.status', Order::COMPLETED)
                ->whereColumn('orders.order_date', '>=', 'individual_offers.start_date')
                ->whereRaw("orders.order_date <= CONCAT(individual_offers.end_date, ' 23:59:59')")
                ->selectRaw('COALESCE(SUM(order_details.quantity), 0)')
        ]);

        if (!empty($filters['search_id'])) {
            $query->where('individual_offers.id', $filters['search_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('active_ingredient', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'individual_offers.id';
        $orderBy = strtolower($filters['order_by'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'product.name' || $sortBy === 'product_display') {
            $query->join('products', 'individual_offers.product_id', '=', 'products.id')
                ->orderBy('products.name', $orderBy)
                ->select('individual_offers.*');
        } else {
            $query->orderBy('individual_offers.id', $orderBy);
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 10;

        return $query->paginate($perPage);
    }

    public function findConflictingOffer(int $productId, string $startDate, string $endDate, ?int $ignoreId = null): ?IndividualOffer
    {
        $query = IndividualOffer::where('product_id', $productId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->where('start_date', '<=', $startDate)
                          ->where('end_date', '>=', $endDate);
                  });
            });

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->first();
    }

    public function create(array $data): IndividualOffer
    {
        return IndividualOffer::create($data);
    }

    public function update(IndividualOffer $individualOffer, array $data): IndividualOffer
    {
        $individualOffer->update($data);
        return $individualOffer;
    }

    public function getOfferAnalytics(IndividualOffer $individualOffer, array $filters = []): array
    {
        $individualOffer->loadMissing(['product.laboratory']);

        // 1. Obtener todas las órdenes que califican para la oferta en su rango de fechas
        $baseOrdersQuery = DB::table('orders')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->where('order_details.product_id', $individualOffer->product_id)
            ->where('orders.status', Order::COMPLETED)
            ->where('orders.order_date', '>=', $individualOffer->start_date)
            ->whereRaw("orders.order_date <= CONCAT(?, ' 23:59:59')", [$individualOffer->end_date]);

        // 2. Vendedoras disponibles (para el filtro del frontend)
        $availableSellers = (clone $baseOrdersQuery)
            ->leftJoin('users', 'orders.seller_id', '=', 'users.id')
            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
            ->whereNotNull('orders.seller_id')
            ->select(
                'orders.seller_id as id',
                DB::raw('COALESCE(CONCAT(employees.name, " ", employees.last_name), users.username, CONCAT("Vendedor #", orders.seller_id)) as name')
            )
            ->distinct()
            ->orderBy('name')
            ->get()
            ->toArray();

        // 3. Aplicar filtro opcional por vendedora si se especificó
        if (!empty($filters['seller_id'])) {
            $baseOrdersQuery->where('orders.seller_id', $filters['seller_id']);
        }

        $orderIds = $baseOrdersQuery->pluck('orders.id')->unique()->values()->toArray();

        if (empty($orderIds)) {
            return [
                'offer' => $individualOffer,
                'kpis' => [
                    'total_units_sold' => 0,
                    'unique_clients_count' => 0,
                    'total_orders_count' => 0,
                    'cross_sell_orders_count' => 0,
                    'single_item_orders_count' => 0,
                    'cross_sell_percentage' => 0,
                    'average_ticket_usd' => 0,
                    'total_orders_amount_usd' => 0,
                    'offer_revenue_usd' => 0,
                    'offer_cost_usd' => 0,
                    'offer_profit_usd' => 0,
                    'offer_profit_margin' => 0,
                    'discount_savings_usd' => 0,
                ],
                'cross_selling_products' => [],
                'sellers_breakdown' => [],
                'available_sellers' => $availableSellers,
            ];
        }

        // 4. Estadísticas a nivel de Órdenes Totales y Costos Totales de las Órdenes
        $orderStats = DB::table('orders')
            ->whereIn('id', $orderIds)
            ->selectRaw('
                COUNT(id) as total_orders_count,
                COUNT(DISTINCT COALESCE(client_id, CONCAT("anon_", id))) as unique_clients_count,
                COALESCE(SUM(total_amount_usd), 0) as total_orders_amount_usd,
                COALESCE(AVG(total_amount_usd), 0) as average_ticket_usd
            ')
            ->first();

        // Costos totales de toda la canasta por orden
        $orderTotalCosts = DB::table('order_details')
            ->whereIn('order_id', $orderIds)
            ->select('order_id', DB::raw('SUM(quantity * unit_cost) as total_order_cost'))
            ->groupBy('order_id')
            ->pluck('total_order_cost', 'order_id')
            ->toArray();

        $totalOrdersAmountUsd = (float) ($orderStats->total_orders_amount_usd ?? 0);
        $totalOrdersCostUsd = (float) array_sum($orderTotalCosts);
        $totalOrdersProfitUsd = round($totalOrdersAmountUsd - $totalOrdersCostUsd, 2);
        $totalOrdersMargin = $totalOrdersAmountUsd > 0 ? round(($totalOrdersProfitUsd / $totalOrdersAmountUsd) * 100, 2) : 0;

        // 5. Estadísticas del ítem en oferta
        $offerItemStats = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->whereIn('order_details.order_id', $orderIds)
            ->where('order_details.product_id', $individualOffer->product_id)
            ->selectRaw('
                COALESCE(SUM(order_details.quantity), 0) as total_units_sold,
                COALESCE(SUM(order_details.quantity * order_details.unit_price_usd), 0) as offer_revenue_usd,
                COALESCE(SUM(order_details.quantity * order_details.unit_cost), 0) as offer_cost_usd,
                COALESCE(SUM(order_details.quantity * GREATEST(0, COALESCE(NULLIF(products.sale_price, 0), order_details.unit_price_usd) - order_details.unit_price_usd)), 0) as discount_savings_usd
            ')
            ->first();

        // 6. Cálculo de venta cruzada (órdenes que contienen otros productos además de la oferta)
        $crossSellOrderIds = DB::table('order_details')
            ->whereIn('order_id', $orderIds)
            ->where(function ($q) use ($individualOffer) {
                $q->where('product_id', '!=', $individualOffer->product_id)
                  ->orWhereNull('product_id');
            })
            ->distinct()
            ->pluck('order_id')
            ->toArray();

        $totalOrdersCount = (int) ($orderStats->total_orders_count ?? 0);
        $crossSellCount = count($crossSellOrderIds);
        $singleItemCount = max(0, $totalOrdersCount - $crossSellCount);
        $crossSellPercentage = $totalOrdersCount > 0 ? round(($crossSellCount / $totalOrdersCount) * 100, 2) : 0;

        $offerRevenue = (float) ($offerItemStats->offer_revenue_usd ?? 0);
        $offerCost = (float) ($offerItemStats->offer_cost_usd ?? 0);
        $offerProfit = round($offerRevenue - $offerCost, 2);
        $offerProfitMargin = $offerRevenue > 0 ? round(($offerProfit / $offerRevenue) * 100, 2) : 0;

        // 7. Top Productos Cruzados (Cross-Selling)
        $crossSellingProducts = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->whereIn('order_details.order_id', $orderIds)
            ->where('order_details.product_id', '!=', $individualOffer->product_id)
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                COALESCE(laboratories.name, "S/L") as laboratory_name,
                SUM(order_details.quantity) as total_quantity,
                COUNT(DISTINCT order_details.order_id) as times_bought_together,
                SUM(order_details.quantity * order_details.unit_price_usd) as total_amount_usd
            ')
            ->groupBy('products.id', 'products.name', 'laboratories.name')
            ->orderByDesc('times_bought_together')
            ->orderByDesc('total_quantity')
            ->limit(15)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'laboratory_name' => $item->laboratory_name,
                    'total_quantity' => (float) $item->total_quantity,
                    'times_bought_together' => (int) $item->times_bought_together,
                    'total_amount_usd' => (float) round($item->total_amount_usd, 2),
                ];
            })
            ->toArray();

        // 8. Rendimiento por Vendedora
        $sellersBreakdown = DB::table('orders')
            ->leftJoin('users', 'orders.seller_id', '=', 'users.id')
            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
            ->join('order_details', function ($join) use ($individualOffer) {
                $join->on('orders.id', '=', 'order_details.order_id')
                     ->where('order_details.product_id', '=', $individualOffer->product_id);
            })
            ->whereIn('orders.id', $orderIds)
            ->selectRaw('
                orders.seller_id,
                COALESCE(CONCAT(employees.name, " ", employees.last_name), users.username, "Sin Vendedor") as seller_name,
                SUM(order_details.quantity) as units_sold,
                COUNT(DISTINCT orders.id) as orders_count,
                SUM(order_details.quantity * order_details.unit_price_usd) as total_usd,
                SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) as profit_usd
            ')
            ->groupBy('orders.seller_id', 'employees.name', 'employees.last_name', 'users.username')
            ->orderByDesc('units_sold')
            ->get()
            ->map(function ($seller) use ($crossSellOrderIds, $orderIds, $orderTotalCosts) {
                $sellerOrders = DB::table('orders')
                    ->whereIn('id', $orderIds)
                    ->where('seller_id', $seller->seller_id)
                    ->select('id', 'total_amount_usd')
                    ->get();

                $sellerOrderIds = $sellerOrders->pluck('id')->toArray();
                $sellerTotalAmount = (float) $sellerOrders->sum('total_amount_usd');
                $sellerTotalCost = (float) array_sum(array_intersect_key($orderTotalCosts, array_flip($sellerOrderIds)));
                $sellerTotalProfit = round($sellerTotalAmount - $sellerTotalCost, 2);

                $crossCount = count(array_intersect($sellerOrderIds, $crossSellOrderIds));
                $totalOrders = (int) $seller->orders_count;
                $crossPercentage = $totalOrders > 0 ? round(($crossCount / $totalOrders) * 100, 2) : 0;

                return [
                    'seller_id' => $seller->seller_id,
                    'seller_name' => $seller->seller_name,
                    'units_sold' => (float) $seller->units_sold,
                    'orders_count' => $totalOrders,
                    'cross_sell_orders_count' => $crossCount,
                    'cross_sell_percentage' => $crossPercentage,
                    'total_usd' => (float) round($sellerTotalAmount, 2),
                    'total_profit_usd' => (float) $sellerTotalProfit,
                    'profit_usd' => (float) round($seller->profit_usd, 2),
                ];
            })
            ->toArray();

        // 9. Historial detallado de todas las órdenes de la oferta
        $ordersHistory = DB::table('orders')
            ->leftJoin('clients', 'orders.client_id', '=', 'clients.id')
            ->leftJoin('users', 'orders.seller_id', '=', 'users.id')
            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
            ->join('order_details', function ($join) use ($individualOffer) {
                $join->on('orders.id', '=', 'order_details.order_id')
                     ->where('order_details.product_id', '=', $individualOffer->product_id);
            })
            ->whereIn('orders.id', $orderIds)
            ->selectRaw('
                orders.id as order_id,
                orders.order_date,
                orders.total_amount_usd,
                COALESCE(CONCAT(employees.name, " ", employees.last_name), users.username, "Sin Vendedor") as seller_name,
                COALESCE(clients.name, "Cliente General") as client_name,
                SUM(order_details.quantity) as offer_quantity,
                SUM(order_details.quantity * order_details.unit_price_usd) as offer_revenue_usd,
                SUM(order_details.quantity * order_details.unit_cost) as offer_cost_usd,
                SUM(order_details.quantity * (order_details.unit_price_usd - order_details.unit_cost)) as offer_profit_usd
            ')
            ->groupBy(
                'orders.id',
                'orders.order_date',
                'orders.total_amount_usd',
                'employees.name',
                'employees.last_name',
                'users.username',
                'clients.name'
            )
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.id')
            ->get()
            ->map(function ($order) use ($crossSellOrderIds, $orderTotalCosts) {
                $offerRev = (float) $order->offer_revenue_usd;
                $offerCost = (float) $order->offer_cost_usd;
                $offerProfit = round($offerRev - $offerCost, 2);
                $offerMargin = $offerRev > 0 ? round(($offerProfit / $offerRev) * 100, 2) : 0;
                $isCrossSell = in_array($order->order_id, $crossSellOrderIds);

                $orderTotalUsd = (float) round($order->total_amount_usd ?? 0, 2);
                $orderTotalCost = (float) ($orderTotalCosts[$order->order_id] ?? 0);
                $orderTotalProfit = round($orderTotalUsd - $orderTotalCost, 2);
                $orderTotalMargin = $orderTotalUsd > 0 ? round(($orderTotalProfit / $orderTotalUsd) * 100, 2) : 0;

                return [
                    'order_id' => $order->order_id,
                    'order_date' => $order->order_date,
                    'seller_name' => $order->seller_name,
                    'client_name' => $order->client_name,
                    'offer_quantity' => (float) $order->offer_quantity,
                    'offer_revenue_usd' => (float) round($offerRev, 2),
                    'offer_cost_usd' => (float) round($offerCost, 2),
                    'offer_profit_usd' => (float) $offerProfit,
                    'offer_margin' => $offerMargin,
                    'order_total_usd' => $orderTotalUsd,
                    'order_total_profit_usd' => $orderTotalProfit,
                    'order_total_margin' => $orderTotalMargin,
                    'is_cross_sell' => $isCrossSell,
                ];
            })
            ->toArray();

        return [
            'offer' => $individualOffer,
            'kpis' => [
                'total_units_sold' => (float) ($offerItemStats->total_units_sold ?? 0),
                'unique_clients_count' => (int) ($orderStats->unique_clients_count ?? 0),
                'total_orders_count' => $totalOrdersCount,
                'cross_sell_orders_count' => $crossSellCount,
                'single_item_orders_count' => $singleItemCount,
                'cross_sell_percentage' => $crossSellPercentage,
                'average_ticket_usd' => (float) round($orderStats->average_ticket_usd ?? 0, 2),
                'total_orders_amount_usd' => $totalOrdersAmountUsd,
                'total_orders_profit_usd' => $totalOrdersProfitUsd,
                'total_orders_margin' => $totalOrdersMargin,
                'offer_revenue_usd' => (float) round($offerRevenue, 2),
                'offer_cost_usd' => (float) round($offerCost, 2),
                'offer_profit_usd' => (float) round($offerProfit, 2),
                'offer_profit_margin' => $offerProfitMargin,
                'discount_savings_usd' => (float) round($offerItemStats->discount_savings_usd ?? 0, 2),
            ],
            'cross_selling_products' => $crossSellingProducts,
            'sellers_breakdown' => $sellersBreakdown,
            'orders_history' => $ordersHistory,
            'available_sellers' => $availableSellers,
        ];
    }

    public function delete(IndividualOffer $individualOffer): bool
    {
        return (bool) $individualOffer->delete();
    }
}
