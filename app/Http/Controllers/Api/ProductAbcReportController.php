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
        $minCoverage = $request->input('min_coverage');
        $maxCoverage = $request->input('max_coverage');

        // Subquery for Period Sales
        $salesQuery = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as period_quantity'),
                // Usamos unit_price_usd * quantity para asegurarnos de que es la venta en USD
                DB::raw('SUM(quantity * unit_price_usd) as period_sales')
            )
            ->where('orders.status', Order::COMPLETED);

        if ($startDate) {
            $salesQuery->whereDate('orders.order_date', '>=', $startDate);
        }
        if ($endDate) {
            $salesQuery->whereDate('orders.order_date', '<=', $endDate);
        }
        $salesQuery->groupBy('product_id');

        // Main Query: Products Left Join Sales
        $query = Product::query()
            ->leftJoinSub($salesQuery, 'period_sales', function ($join) {
                $join->on('products.id', '=', 'period_sales.product_id');
            })
            ->select(
                'products.id',
                'products.name',
                'products.stock',
                'products.sales_average', // Venta Promedio Mensual (calculated elsewhere or stored)
                DB::raw('COALESCE(period_sales.period_quantity, 0) as total_quantity'),
                DB::raw('COALESCE(period_sales.period_sales, 0) as total_sales')
            )
            // Filter out products with no stock AND no sales (irrelevant)
            ->where(function ($q) {
                $q->where('products.stock', '>', 0)
                    ->orWhere('period_sales.period_sales', '>', 0);
            });

        $results = $query->orderBy('total_sales', 'desc')->get();

        $grandTotal = $results->sum('total_sales');

        $accumulatedSales = 0;
        $processedData = $results->map(function ($item) use ($grandTotal, &$accumulatedSales) {
            $sales = (float) $item->total_sales;
            $avgSales = (float) $item->sales_average;
            $stock = (int) $item->stock;

            // Coverage Calculation
            $coverage = 0;
            if ($avgSales > 0) {
                $coverage = $stock / $avgSales;
            } elseif ($stock > 0) {
                // Stock > 0 but AvgSales = 0 => Infinite coverage (Dead Stock effectively)
                // We can represent this as high number or handle specifically
                $coverage = 9999;
            }

            // Dead Stock Flag
            $isDeadStock = ($stock > 0 && $avgSales == 0);

            // ABC Logic
            $accumulatedSales += $sales;
            $participation = $grandTotal > 0 ? ($sales / $grandTotal) * 100 : 0;
            $accumulatedPct = $grandTotal > 0 ? ($accumulatedSales / $grandTotal) * 100 : 0;

            $classification = 'C';
            if ($accumulatedPct <= 80) {
                $classification = 'A';
            } elseif ($accumulatedPct <= 95) {
                $classification = 'B';
            }

            // If no sales, force C? Or keep as C since sales are 0.
            // Items with 0 sales naturally fall to bottom/C.

            return [
                'id' => $item->id,
                'name' => $item->name,
                'stock' => $stock,
                'sales_average' => $avgSales,
                'coverage_months' => $isDeadStock ? null : round($coverage, 1),
                'is_dead_stock' => $isDeadStock,
                'total_quantity' => (int) $item->total_quantity,
                'total_sales' => round($sales, 2),
                'participation_percentage' => round($participation, 2),
                'accumulated_percentage' => round($accumulatedPct, 2),
                'classification' => $classification,
            ];
        });

        // Filter by Classification
        if ($classificationFilter) {
            $processedData = $processedData->filter(function ($item) use ($classificationFilter) {
                return $item['classification'] === $classificationFilter;
            });
        }

        // Filter by Coverage Range
        $coverageRange = $request->input('coverage_range');
        if ($coverageRange) {
            $processedData = $processedData->filter(function ($item) use ($coverageRange) {
                $cov = $item['coverage_months'];
                $isDead = $item['is_dead_stock'];

                switch ($coverageRange) {
                    case 'dead_stock':
                        return $isDead;
                    case 'critical': // < 1 month
                        return !$isDead && $cov < 1;
                    case 'low': // 1 - 2 months
                        return !$isDead && $cov >= 1 && $cov < 2;
                    case 'optimal': // 2 - 4 months
                        return !$isDead && $cov >= 2 && $cov <= 4;
                    case 'excess': // > 4 months
                        return !$isDead && $cov > 4;
                    default:
                        return true;
                }
            });
        }

        return response()->json([
            'data' => $processedData->values(),
            'grand_total' => round($grandTotal, 2),
            'total_items' => $processedData->count()
        ]);
    }
}
