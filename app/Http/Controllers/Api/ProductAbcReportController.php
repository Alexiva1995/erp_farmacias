<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductAbcReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $classificationFilter = $request->input('classification');

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

        $grandTotal = $results->sum('total_sales');

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

        if ($classificationFilter) {
            $processedData = $processedData->filter(function ($item) use ($classificationFilter) {
                return $item['classification'] === $classificationFilter;
            })->values();
        }

        return response()->json([
            'data' => $processedData,
            'grand_total' => round($grandTotal, 2),
            'total_items' => $processedData->count()
        ]);
    }
}
