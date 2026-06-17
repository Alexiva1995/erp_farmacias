<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductStatsService
{
    /**
     * Obtiene las estadísticas de ventas de un producto con visión competitiva.
     */
    public function getProductSalesStats(Product $product): array
    {
        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';
        $isIngredient = (bool) $product->no_pvp || $isRestaurant;

        if ($isIngredient) {
            // 1. Unidades totales consumidas (Histórico)
            $totalUnitsSold = DB::table('inventory_movements')
                ->where('product_id', $product->id)
                ->where('movement_type', 'sale')
                ->where('quantity', '<', 0)
                ->sum(DB::raw('ABS(quantity)')) ?: 0;

            // 2. Último consumo
            $lastSale = DB::table('inventory_movements')
                ->where('product_id', $product->id)
                ->where('movement_type', 'sale')
                ->where('quantity', '<', 0)
                ->select('movement_date as order_date', DB::raw('0 as price'), DB::raw('ABS(quantity) as quantity'))
                ->orderBy('movement_date', 'desc')
                ->first();

            // 3. Promedio mensual lineal (Últimos 12 meses fijos)
            $totalSoldLastYear = DB::table('inventory_movements')
                ->where('product_id', $product->id)
                ->where('movement_type', 'sale')
                ->where('quantity', '<', 0)
                ->where('movement_date', '>=', now()->subYear())
                ->sum(DB::raw('ABS(quantity)')) ?: 0;

            $monthlyAverage = $totalSoldLastYear / 12;

            // 4. Datos del Grupo y Market Share de Consumo
            $marketShare = 0;
            if ($product->group_id) {
                $totalGroupSales = DB::table('inventory_movements')
                    ->join('products', 'inventory_movements.product_id', '=', 'products.id')
                    ->where('products.group_id', $product->group_id)
                    ->where('inventory_movements.movement_type', 'sale')
                    ->where('inventory_movements.quantity', '<', 0)
                    ->sum(DB::raw('ABS(inventory_movements.quantity)'));

                if ($totalGroupSales > 0) {
                    $marketShare = ($totalUnitsSold / $totalGroupSales) * 100;
                }
            }
        } else {
            // 1. Unidades totales vendidas (Histórico)
            $totalUnitsSold = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('order_details.product_id', $product->id)
                ->where('orders.status', 'Completed')
                ->sum('order_details.quantity') ?: 0;

            // 2. Última venta
            $lastSale = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('order_details.product_id', $product->id)
                ->where('orders.status', 'Completed')
                ->select('orders.order_date', 'order_details.price', 'order_details.quantity')
                ->orderBy('orders.order_date', 'desc')
                ->first();

            // 3. Promedio mensual lineal (Últimos 12 meses fijos)
            $totalSoldLastYear = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('order_details.product_id', $product->id)
                ->where('orders.status', 'Completed')
                ->where('orders.order_date', '>=', now()->subYear())
                ->sum('order_details.quantity') ?: 0;

            $monthlyAverage = $totalSoldLastYear / 12;

            // 4. Datos del Grupo y Market Share
            $marketShare = 0;
            if ($product->group_id) {
                $totalGroupSales = DB::table('order_details')
                    ->join('products', 'order_details.product_id', '=', 'products.id')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->where('products.group_id', $product->group_id)
                    ->where('orders.status', 'Completed')
                    ->sum('order_details.quantity');

                if ($totalGroupSales > 0) {
                    $marketShare = ($totalUnitsSold / $totalGroupSales) * 100;
                }
            }
        }

        // 5. Tendencia Histórica
        $chartData = $this->getHistoricalGroupTrend($product);

        return [
            'total_units_sold' => (float) $totalUnitsSold,
            'last_sale' => $lastSale ? [
                'date' => $lastSale->order_date,
                'price' => (float) $lastSale->price,
                'quantity' => (float) $lastSale->quantity,
            ] : null,
            'monthly_average' => round((float) $monthlyAverage, 2),
            'market_share' => round((float) $marketShare, 2),
            'trend_chart' => $chartData,
            'is_ingredient' => $isIngredient,
        ];
    }

    /**
     * Obtiene la tendencia histórica completa del producto y su competencia.
     */
    private function getHistoricalGroupTrend(Product $product): array
    {
        $groupId = $product->group_id;
        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';
        $isIngredient = (bool) $product->no_pvp || $isRestaurant;
        
        if ($isIngredient) {
            $queryBase = DB::table('inventory_movements')
                ->where('movement_type', 'sale')
                ->where('quantity', '<', 0);

            if ($groupId) {
                $queryBase->join('products', 'inventory_movements.product_id', '=', 'products.id')
                    ->where('products.group_id', $groupId);
            } else {
                $queryBase->where('inventory_movements.product_id', $product->id);
            }

            $minDateString = $queryBase->min('inventory_movements.movement_date');
        } else {
            $queryBase = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('orders.status', 'Completed');

            if ($groupId) {
                $queryBase->join('products', 'order_details.product_id', '=', 'products.id')
                    ->where('products.group_id', $groupId);
            } else {
                $queryBase->where('order_details.product_id', $product->id);
            }

            $minDateString = $queryBase->min('orders.order_date');
        }

        $startDate = $minDateString ? Carbon::parse($minDateString)->startOfMonth() : now()->subMonths(6)->startOfMonth();
        $endDate = now()->startOfMonth();

        // Generar periodos mensuales
        $periods = [];
        $current = clone $startDate;
        while ($current <= $endDate) {
            $periods[] = [
                'month' => $current->month,
                'year' => $current->year,
                'label' => $current->translatedFormat('M y'),
            ];
            $current->addMonth();
        }

        // Limitar a máximo 24 periodos para legibilidad (si es muy antiguo)
        if (count($periods) > 24) {
             $periods = array_slice($periods, -24);
        }

        $labels = array_column($periods, 'label');
        $series = [];

        // 1. Serie del Producto Principal
        $series[] = [
            'name' => $product->name,
            'data' => $this->getMonthlyDataSeries($product->id, $periods, $isIngredient),
            'is_main' => true
        ];

        // 2. Si tiene grupo, buscar competidores
        if ($groupId) {
            if ($isIngredient) {
                $competitors = DB::table('products')
                    ->join('inventory_movements', 'products.id', '=', 'inventory_movements.product_id')
                    ->where('products.group_id', $groupId)
                    ->where('products.id', '!=', $product->id)
                    ->where('inventory_movements.movement_type', 'sale')
                    ->where('inventory_movements.quantity', '<', 0)
                    ->select('products.id', 'products.name', DB::raw('SUM(ABS(inventory_movements.quantity)) as total'))
                    ->groupBy('products.id', 'products.name')
                    ->orderBy('total', 'desc')
                    ->limit(5)
                    ->get();
            } else {
                $competitors = DB::table('products')
                    ->join('order_details', 'products.id', '=', 'order_details.product_id')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->where('products.group_id', $groupId)
                    ->where('products.id', '!=', $product->id)
                    ->where('orders.status', 'Completed')
                    ->select('products.id', 'products.name', DB::raw('SUM(order_details.quantity) as total'))
                    ->groupBy('products.id', 'products.name')
                    ->orderBy('total', 'desc')
                    ->limit(5)
                    ->get();
            }

            foreach ($competitors as $competitor) {
                $series[] = [
                    'name' => $competitor->name,
                    'data' => $this->getMonthlyDataSeries($competitor->id, $periods, $isIngredient),
                    'is_main' => false
                ];
            }

            // 3. Agrupar el resto en "Otros"
            $otherProductsIds = DB::table('products')
                ->where('group_id', $groupId)
                ->whereNotIn('id', array_merge([$product->id], $competitors->pluck('id')->toArray()))
                ->pluck('id');

            if ($otherProductsIds->isNotEmpty()) {
                $series[] = [
                    'name' => 'Otros Competidores',
                    'data' => $this->getMonthlyDataSeriesForGroup($otherProductsIds, $periods, $isIngredient),
                    'is_main' => false
                ];
            }
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }

    private function getMonthlyDataSeries(int $productId, array $periods, bool $isIngredient = false): array
    {
        $data = [];
        foreach ($periods as $p) {
            if ($isIngredient) {
                $quantity = DB::table('inventory_movements')
                    ->where('product_id', $productId)
                    ->where('movement_type', 'sale')
                    ->where('quantity', '<', 0)
                    ->whereMonth('movement_date', $p['month'])
                    ->whereYear('movement_date', $p['year'])
                    ->sum(DB::raw('ABS(quantity)'));
            } else {
                $quantity = DB::table('order_details')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->where('order_details.product_id', $productId)
                    ->where('orders.status', 'Completed')
                    ->whereMonth('orders.order_date', $p['month'])
                    ->whereYear('orders.order_date', $p['year'])
                    ->sum('order_details.quantity');
            }
            
            $data[] = (float) $quantity;
        }
        return $data;
    }

    private function getMonthlyDataSeriesForGroup($productIds, array $periods, bool $isIngredient = false): array
    {
        $data = [];
        foreach ($periods as $p) {
            if ($isIngredient) {
                $quantity = DB::table('inventory_movements')
                    ->whereIn('product_id', $productIds)
                    ->where('movement_type', 'sale')
                    ->where('quantity', '<', 0)
                    ->whereMonth('movement_date', $p['month'])
                    ->whereYear('movement_date', $p['year'])
                    ->sum(DB::raw('ABS(quantity)'));
            } else {
                $quantity = DB::table('order_details')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->whereIn('order_details.product_id', $productIds)
                    ->where('orders.status', 'Completed')
                    ->whereMonth('orders.order_date', $p['month'])
                    ->whereYear('orders.order_date', $p['year'])
                    ->sum('order_details.quantity');
            }
            
            $data[] = (float) $quantity;
        }
        return $data;
    }
}

