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
        // 1. Unidades totales vendidas (Histórico)
        $totalUnitsSold = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('order_details.product_id', $product->id)
            ->where('orders.status', 'Completed')
            ->sum('order_details.quantity');

        // 2. Última venta
        $lastSale = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('order_details.product_id', $product->id)
            ->where('orders.status', 'Completed')
            ->select('orders.order_date', 'order_details.price', 'order_details.quantity')
            ->orderBy('orders.order_date', 'desc')
            ->first();

        // 3. Promedio mensual (Últimos 12 meses)
        $monthlyAverage = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('order_details.product_id', $product->id)
            ->where('orders.status', 'Completed')
            ->where('orders.order_date', '>=', now()->subYear())
            ->select(DB::raw('SUM(order_details.quantity) as total_quantity'))
            ->groupBy(DB::raw('MONTH(orders.order_date)'))
            ->get()
            ->avg('total_quantity') ?: 0;

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

        // 5. Tendencia Histórica (Desde primera venta del grupo)
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
        ];
    }

    /**
     * Obtiene la tendencia histórica completa del producto y su competencia.
     */
    private function getHistoricalGroupTrend(Product $product): array
    {
        $groupId = $product->group_id;
        
        // Determinar fecha de inicio (primera venta registrada para cualquiera del grupo)
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
            'data' => $this->getMonthlyDataSeries($product->id, $periods),
            'is_main' => true
        ];

        // 2. Si tiene grupo, buscar competidores
        if ($groupId) {
            // Obtener los Top 5 competidores más vendidos (excluyendo el actual)
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

            foreach ($competitors as $competitor) {
                $series[] = [
                    'name' => $competitor->name,
                    'data' => $this->getMonthlyDataSeries($competitor->id, $periods),
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
                    'data' => $this->getMonthlyDataSeriesForGroup($otherProductsIds, $periods),
                    'is_main' => false
                ];
            }
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }

    private function getMonthlyDataSeries(int $productId, array $periods): array
    {
        $data = [];
        foreach ($periods as $p) {
            $quantity = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('order_details.product_id', $productId)
                ->where('orders.status', 'Completed')
                ->whereMonth('orders.order_date', $p['month'])
                ->whereYear('orders.order_date', $p['year'])
                ->sum('order_details.quantity');
            
            $data[] = (float) $quantity;
        }
        return $data;
    }

    private function getMonthlyDataSeriesForGroup($productIds, array $periods): array
    {
        $data = [];
        foreach ($periods as $p) {
            $quantity = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereIn('order_details.product_id', $productIds)
                ->where('orders.status', 'Completed')
                ->whereMonth('orders.order_date', $p['month'])
                ->whereYear('orders.order_date', $p['year'])
                ->sum('order_details.quantity');
            
            $data[] = (float) $quantity;
        }
        return $data;
    }
}

