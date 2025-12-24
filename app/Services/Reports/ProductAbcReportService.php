<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;

class ProductAbcReportService
{
    /**
     * Generates the ABC Inventory Analysis Report.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $classificationFilter
     * @return array
     */
    public function generateReport(?string $startDate, ?string $endDate, ?string $classificationFilter): array
    {
        // 1. Query Data
        $query = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_details.quantity) as total_quantity'),
                DB::raw('SUM(order_details.price) as total_sales')
            )
            ->where('orders.status', Order::COMPLETED)
            ->groupBy('products.id', 'products.name');

        if ($startDate) {
            $query->whereDate('orders.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('orders.created_at', '<=', $endDate);
        }

        $results = $query->orderBy('total_sales', 'desc')->get();

        // 2. Calculate Grand Total
        $grandTotal = $results->sum('total_sales');

        // 3. Process ABC Classification
        $accumulatedSales = 0;
        $processedData = $results->map(function ($item) use ($grandTotal, &$accumulatedSales) {
            $sales = (float) $item->total_sales;
            $accumulatedSales += $sales;

            $participation = $grandTotal > 0 ? ($sales / $grandTotal) * 100 : 0;
            $accumulatedPct = $grandTotal > 0 ? ($accumulatedSales / $grandTotal) * 100 : 0;

            $classification = 'C';
            if ($accumulatedPct <= 80) {
                $classification = 'A';
            } elseif ($accumulatedPct <= 95) {
                $classification = 'B';
            }

            return [
                'id' => $item->id,
                'name' => $item->name,
                'total_quantity' => (int) $item->total_quantity,
                'total_sales' => round($sales, 2),
                'participation_percentage' => round($participation, 2),
                'accumulated_percentage' => round($accumulatedPct, 2),
                'classification' => $classification,
            ];
        });

        // 4. Apply Classification Filter
        if ($classificationFilter) {
            $processedData = $processedData->filter(function ($item) use ($classificationFilter) {
                return $item['classification'] === $classificationFilter;
            })->values();
        }

        return [
            'data' => $processedData,
            'grand_total' => round($grandTotal, 2),
            'total_items' => $processedData->count()
        ];
    }
}
