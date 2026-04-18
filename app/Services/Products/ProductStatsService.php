<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductStatsService
{
    /**
     * Obtiene las estadísticas de ventas de un producto.
     */
    public function getProductSalesStats(Product $product): array
    {
        // 1. Unidades totales vendidas
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

        // 3. Promedio mensual (basado en los últimos 12 meses con ventas)
        $monthlyAverage = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('order_details.product_id', $product->id)
            ->where('orders.status', 'Completed')
            ->where('orders.order_date', '>=', now()->subYear())
            ->select(DB::raw('SUM(order_details.quantity) as total_quantity'))
            ->groupBy(DB::raw('MONTH(orders.order_date)'))
            ->get()
            ->avg('total_quantity') ?: 0;

        // 4. Datos para el gráfico (últimos 6 meses)
        $chartData = $this->getTrendData($product->id);

        return [
            'total_units_sold' => (float) $totalUnitsSold,
            'last_sale' => $lastSale ? [
                'date' => $lastSale->order_date,
                'price' => (float) $lastSale->price,
                'quantity' => (float) $lastSale->quantity,
            ] : null,
            'monthly_average' => round((float) $monthlyAverage, 2),
            'trend_chart' => $chartData,
        ];
    }

    /**
     * Obtiene la tendencia de ventas de los últimos 6 meses.
     */
    private function getTrendData(int $productId): array
    {
        $months = [];
        $labels = [];
        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->month;
            $year = $date->year;
            
            $months[] = [
                'month' => $month,
                'year' => $year,
                'label' => $date->translatedFormat('M y'),
            ];
        }

        foreach ($months as $m) {
            $quantity = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('order_details.product_id', $productId)
                ->where('orders.status', 'Completed')
                ->whereMonth('orders.order_date', $m['month'])
                ->whereYear('orders.order_date', $m['year'])
                ->sum('order_details.quantity');

            $labels[] = $m['label'];
            $series[] = (float) $quantity;
        }

        return [
            'labels' => $labels,
            'series' => [
                [
                    'name' => 'Unidades Vendidas',
                    'data' => $series,
                ]
            ],
        ];
    }
}
