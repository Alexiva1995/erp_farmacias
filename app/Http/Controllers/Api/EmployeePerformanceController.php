<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\EmployeePerformance\EmployeePerformanceQueryService;
use App\Models\Order;
use App\Models\SaleCount;
use App\Models\OrderDetail;
use App\Models\Employee;
use App\Models\InvoiceCount;
use App\Models\ProductCount;
use App\Models\InventoryCycle;
use App\Http\Requests\Employee\LockPerformanceMonthRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeePerformanceController extends Controller
{
    public function __construct(
        protected EmployeePerformanceQueryService $performanceQueryService
    ) {
    }

    /**
     * Get active employees for monthly performance evaluation.
     */
    public function index(Request $request)
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        try {
            $employees = $this->performanceQueryService->getEmployeesWithPerformance($month, $year);

            return response()->json([
                'status' => true,
                'message' => 'Empleados recuperados exitosamente',
                'data' => $employees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al obtener métricas de empleados: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Lock/Snapshot the performance data for a given month and year.
     */
    public function lockMonth(LockPerformanceMonthRequest $request)
    {
        try {
            $success = $this->performanceQueryService->captureSnapshot(
                $request->month,
                $request->year
            );

            return response()->json([
                'status' => $success,
                'message' => $success ? 'Mes cerrado y datos persistidos exitosamente' : 'Error al cerrar el mes',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al cerrar el mes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get performance data for a specific employee.
     */
    public function getPerformance(Employee $employee)
    {
        try {
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year;

            // Depuración: Verificar datos del empleado

            // Usar la misma lógica que el servicio funcional
            $currentMonthSales = $this->calculateSales($employee->user_id, $currentMonth, $currentYear);
            
            // Calcular datos históricos acumulados (desde 2026)
            $historicalOrders = Order::where('seller_id', $employee->user_id)
                ->whereDate('order_date', '>=', '2026-01-01 00:00:00')
                ->where(function($query) {
                    $query->where('status', 'Completed')
                          ->orWhereNotNull('completed_at');
                })
                ->get();

            $historicalTotal = $historicalOrders->sum('total_amount_usd');
            $historicalUnits = OrderDetail::whereIn('order_id', $historicalOrders->pluck('id'))
                ->sum('quantity');

            // Depuración: Verificar datos históricos

            $historicalTicketAvg = $historicalOrders->count() > 0 ? 
                $historicalTotal / $historicalOrders->count() : 0;

            $historicalUnitsAvg = $historicalOrders->count() > 0 ? 
                $historicalUnits / $historicalOrders->count() : 0;

            // Calcular métricas del mes actual
            $currentMonthOrders = Order::where('seller_id', $employee->user_id)
                ->whereMonth('order_date', $currentMonth)
                ->whereYear('order_date', $currentYear)
                ->where(function($query) {
                    $query->where('status', 'Completed')
                          ->orWhereNotNull('completed_at');
                })
                ->get();

            $currentMonthUnits = OrderDetail::whereIn('order_id', $currentMonthOrders->pluck('id'))
                ->sum('quantity');

            // Depuración: Verificar datos del mes actual

            $currentMonthTicketAvg = $currentMonthOrders->count() > 0 ? 
                $currentMonthSales / $currentMonthOrders->count() : 0;

            $currentMonthUnitsAvg = $currentMonthOrders->count() > 0 ? 
                $currentMonthUnits / $currentMonthOrders->count() : 0;

            // Rankings
            $rankings = $this->getRankings($employee->user_id, $currentMonth, $currentYear);
            
            // Datos de inventario
            $inventoryCounts = $this->getInventoryCounts($employee->user_id);

            // Calcular métricas de rentabilidad reales
            $profitability = $this->getProfitabilityMetrics($employee->user_id, $currentMonth, $currentYear);

            // Calcular órdenes con un solo producto (para Cross-selling)
            $currentMonthSingleProductOrders = 0;
            foreach ($currentMonthOrders as $order) {
                $uniqueCount = OrderDetail::where('order_id', $order->id)->distinct('product_id')->count();
                if ($uniqueCount === 1) {
                    $currentMonthSingleProductOrders++;
                }
            }

            $historicalSingleProductOrders = 0;
            foreach ($historicalOrders as $order) {
                $uniqueCount = OrderDetail::where('order_id', $order->id)->distinct('product_id')->count();
                if ($uniqueCount === 1) {
                    $historicalSingleProductOrders++;
                }
            }

            return ApiResponse::success([
                'salesMetrics' => [
                    'currentMonth' => [
                        'totalAmount' => $currentMonthSales,
                        'totalUnits' => $currentMonthUnits,
                        'ticketAverage' => $currentMonthTicketAvg,
                        'unitsAverage' => $currentMonthUnitsAvg,
                        'totalOrders' => $currentMonthOrders->count(),
                        'ordersWithSingleProduct' => $currentMonthSingleProductOrders
                    ],
                    'historical' => [
                        'totalAmount' => $historicalTotal,
                        'totalUnits' => $historicalUnits,
                        'ticketAverage' => $historicalTicketAvg,
                        'unitsAverage' => $historicalUnitsAvg,
                        'totalOrders' => $historicalOrders->count(),
                        'ordersWithSingleProduct' => $historicalSingleProductOrders
                    ]
                ],
                'profitabilityMetrics' => $profitability,
                'rankings' => $rankings,
                'inventoryCounts' => $inventoryCounts
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en getPerformance: ' . $e->getMessage());
            return ApiResponse::error($e->getMessage());
        }
    }

    // Usar el mismo método calculateSales que funciona en el servicio
    private function calculateSales(int $userId, int $month, int $year): float
    {
        return (float) Order::where('seller_id', $userId)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->whereDate('order_date', '>=', '2026-01-01 00:00:00')
            ->where(function($query) {
                $query->where('status', 'Completed')
                      ->orWhereNotNull('completed_at');
            })
            ->sum('total_amount_usd');
    }

    private function getSalesMetrics($userId, $month, $year)
    {
        // Histórico completo
        $historicalOrders = Order::where('seller_id', $userId)
            ->whereIn('status', ['Completed', 'Closed'])
            ->get();

        $historicalTotal = $historicalOrders->sum(function($order) {
            return $order->usd_conversion ? $order->total_amount_usd : $order->total_amount;
        });

        $historicalUnits = OrderDetail::whereIn('order_id', $historicalOrders->pluck('id'))
            ->sum('quantity');

        $historicalTicketAvg = $historicalOrders->count() > 0 ? 
            $historicalTotal / $historicalOrders->count() : 0;

        $historicalUnitsAvg = $historicalOrders->count() > 0 ? 
            $historicalUnits / $historicalOrders->count() : 0;

        // Mes actual
        $currentMonthOrders = Order::where('seller_id', $userId)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->whereIn('status', ['Completed', 'Closed'])
            ->get();

        $currentMonthTotal = $currentMonthOrders->sum(function($order) {
            return $order->usd_conversion ? $order->total_amount_usd : $order->total_amount;
        });

        $currentMonthUnits = OrderDetail::whereIn('order_id', $currentMonthOrders->pluck('id'))
            ->sum('quantity');

        $currentMonthTicketAvg = $currentMonthOrders->count() > 0 ? 
            $currentMonthTotal / $currentMonthOrders->count() : 0;

        $currentMonthUnitsAvg = $currentMonthOrders->count() > 0 ? 
            $currentMonthUnits / $currentMonthOrders->count() : 0;

        return [
            'historical' => [
                'totalAmount' => $historicalTotal,
                'totalUnits' => $historicalUnits,
                'ticketAverage' => $historicalTicketAvg,
                'unitsAverage' => $historicalUnitsAvg,
                'totalOrders' => $historicalOrders->count()
            ],
            'currentMonth' => [
                'totalAmount' => $currentMonthTotal,
                'totalUnits' => $currentMonthUnits,
                'ticketAverage' => $currentMonthTicketAvg,
                'unitsAverage' => $currentMonthUnitsAvg,
                'totalOrders' => $currentMonthOrders->count()
            ]
        ];
    }

    private function getProfitabilityMetrics($userId, $month, $year)
    {
        // Histórico completo
        $historicalOrders = Order::where('seller_id', $userId)
            ->whereIn('status', ['Completed', 'Closed'])
            ->get();

        // Tasa de UP-selling (órdenes con >1 producto distinto)
        $historicalUpsellCount = 0;
        foreach ($historicalOrders as $order) {
            $uniqueProducts = OrderDetail::where('order_id', $order->id)
                ->distinct('product_id')
                ->count();
            if ($uniqueProducts > 1) {
                $historicalUpsellCount++;
            }
        }
        $historicalUpsellRate = $historicalOrders->count() > 0 ? 
            ($historicalUpsellCount / $historicalOrders->count()) * 100 : 0;

        // Tiempo promedio de orden
        $historicalCompletedOrders = Order::where('seller_id', $userId)
            ->whereNotNull('completed_at')
            ->get();
        
        $historicalAvgTime = 0;
        if ($historicalCompletedOrders->count() > 0) {
            $totalTime = $historicalCompletedOrders->sum(function($order) {
                return $order->created_at->diffInMinutes($order->completed_at);
            });
            $historicalAvgTime = $totalTime / $historicalCompletedOrders->count();
        }

        // % de Devoluciones
        $historicalReturnedOrders = Order::where('seller_id', $userId)
            ->where('status', 'Returned')
            ->count();
        
        $historicalReturnRate = $historicalOrders->count() > 0 ? 
            ($historicalReturnedOrders / $historicalOrders->count()) * 100 : 0;

        // Mes actual
        $currentMonthOrders = Order::where('seller_id', $userId)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->whereIn('status', ['Completed', 'Closed'])
            ->get();

        // Tasa de UP-selling mes actual
        $currentUpsellCount = 0;
        foreach ($currentMonthOrders as $order) {
            $uniqueProducts = OrderDetail::where('order_id', $order->id)
                ->distinct('product_id')
                ->count();
            if ($uniqueProducts > 1) {
                $currentUpsellCount++;
            }
        }
        $currentUpsellRate = $currentMonthOrders->count() > 0 ? 
            ($currentUpsellCount / $currentMonthOrders->count()) * 100 : 0;

        // Tiempo promedio mes actual
        $currentCompletedOrders = Order::where('seller_id', $userId)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->whereNotNull('completed_at')
            ->get();
        
        $currentAvgTime = 0;
        if ($currentCompletedOrders->count() > 0) {
            $totalTime = $currentCompletedOrders->sum(function($order) {
                return $order->created_at->diffInMinutes($order->completed_at);
            });
            $currentAvgTime = $totalTime / $currentCompletedOrders->count();
        }

        // % de Devoluciones mes actual
        $currentReturnedOrders = Order::where('seller_id', $userId)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->where('status', 'Returned')
            ->count();
        
        $currentReturnRate = $currentMonthOrders->count() > 0 ? 
            ($currentReturnedOrders / $currentMonthOrders->count()) * 100 : 0;

        return [
            'historical' => [
                'upsellRate' => round($historicalUpsellRate, 2),
                'avgOrderTime' => round($historicalAvgTime, 2), // en minutos
                'returnRate' => round($historicalReturnRate, 2)
            ],
            'currentMonth' => [
                'upsellRate' => round($currentUpsellRate, 2),
                'avgOrderTime' => round($currentAvgTime, 2), // en minutos
                'returnRate' => round($currentReturnRate, 2)
            ]
        ];
    }

    private function getRankings($userId, $month, $year)
    {
        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';

        if ($isRestaurant) {
            // Top 10 Platos por unidades (histórico)
            $topProductsByUnits = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('dishes', 'order_details.dish_id', '=', 'dishes.id')
                ->where('orders.seller_id', $userId)
                ->whereDate('orders.order_date', '>=', '2026-01-01 00:00:00')
                ->where(function($query) {
                    $query->where('orders.status', 'Completed')
                          ->orWhereNotNull('orders.completed_at');
                })
                ->selectRaw('
                    dishes.id,
                    dishes.name,
                    SUM(order_details.quantity) as units,
                    SUM(order_details.quantity * order_details.unit_price_usd) as amount
                ')
                ->groupBy('dishes.id', 'dishes.name')
                ->orderByDesc('units')
                ->limit(10)
                ->get();

            // Top 10 Platos por monto (histórico)
            $topProductsByAmount = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('dishes', 'order_details.dish_id', '=', 'dishes.id')
                ->where('orders.seller_id', $userId)
                ->whereDate('orders.order_date', '>=', '2026-01-01 00:00:00')
                ->where(function($query) {
                    $query->where('orders.status', 'Completed')
                          ->orWhereNotNull('orders.completed_at');
                })
                ->selectRaw('
                    dishes.id,
                    dishes.name,
                    SUM(order_details.quantity) as units,
                    SUM(order_details.quantity * order_details.unit_price_usd) as amount
                ')
                ->groupBy('dishes.id', 'dishes.name')
                ->orderByDesc('amount')
                ->limit(10)
                ->get();

            // Top 5 Categorías de platos por unidades (histórico)
            $topLabsByUnits = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('dishes', 'order_details.dish_id', '=', 'dishes.id')
                ->join('categories', 'dishes.category_id', '=', 'categories.id')
                ->where('orders.seller_id', $userId)
                ->whereDate('orders.order_date', '>=', '2026-01-01 00:00:00')
                ->where(function($query) {
                    $query->where('orders.status', 'Completed')
                          ->orWhereNotNull('orders.completed_at');
                })
                ->selectRaw('
                    categories.id,
                    categories.name,
                    SUM(order_details.quantity) as units,
                    SUM(order_details.quantity * order_details.unit_price_usd) as amount
                ')
                ->groupBy('categories.id', 'categories.name')
                ->orderByDesc('units')
                ->limit(5)
                ->get();

            // Top 5 Categorías de platos por monto (histórico)
            $topLabsByAmount = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('dishes', 'order_details.dish_id', '=', 'dishes.id')
                ->join('categories', 'dishes.category_id', '=', 'categories.id')
                ->where('orders.seller_id', $userId)
                ->whereDate('orders.order_date', '>=', '2026-01-01 00:00:00')
                ->where(function($query) {
                    $query->where('orders.status', 'Completed')
                          ->orWhereNotNull('orders.completed_at');
                })
                ->selectRaw('
                    categories.id,
                    categories.name,
                    SUM(order_details.quantity) as units,
                    SUM(order_details.quantity * order_details.unit_price_usd) as amount
                ')
                ->groupBy('categories.id', 'categories.name')
                ->orderByDesc('amount')
                ->limit(5)
                ->get();
        } else {
            // Top 10 Productos por unidades (histórico)
            $topProductsByUnits = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->where('orders.seller_id', $userId)
                ->whereDate('orders.order_date', '>=', '2026-01-01 00:00:00')
                ->where(function($query) {
                    $query->where('orders.status', 'Completed')
                          ->orWhereNotNull('orders.completed_at');
                })
                ->selectRaw('
                    products.id,
                    products.name,
                    SUM(order_details.quantity) as units,
                    SUM(order_details.quantity * order_details.unit_price_usd) as amount
                ')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('units')
                ->limit(10)
                ->get();

            // Top 10 Productos por monto (histórico)
            $topProductsByAmount = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->where('orders.seller_id', $userId)
                ->whereDate('orders.order_date', '>=', '2026-01-01 00:00:00')
                ->where(function($query) {
                    $query->where('orders.status', 'Completed')
                          ->orWhereNotNull('orders.completed_at');
                })
                ->selectRaw('
                    products.id,
                    products.name,
                    SUM(order_details.quantity) as units,
                    SUM(order_details.quantity * order_details.unit_price_usd) as amount
                ')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('amount')
                ->limit(10)
                ->get();

            // Top 5 Laboratorios por unidades (histórico)
            $topLabsByUnits = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                ->where('orders.seller_id', $userId)
                ->whereDate('orders.order_date', '>=', '2026-01-01 00:00:00')
                ->where(function($query) {
                    $query->where('orders.status', 'Completed')
                          ->orWhereNotNull('orders.completed_at');
                })
                ->selectRaw('
                    laboratories.id,
                    laboratories.name,
                    SUM(order_details.quantity) as units,
                    SUM(order_details.quantity * order_details.unit_price_usd) as amount
                ')
                ->groupBy('laboratories.id', 'laboratories.name')
                ->orderByDesc('units')
                ->limit(5)
                ->get();

            // Top 5 Laboratorios por monto (histórico)
            $topLabsByAmount = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                ->where('orders.seller_id', $userId)
                ->whereDate('orders.order_date', '>=', '2026-01-01 00:00:00')
                ->where(function($query) {
                    $query->where('orders.status', 'Completed')
                          ->orWhereNotNull('orders.completed_at');
                })
                ->selectRaw('
                    laboratories.id,
                    laboratories.name,
                    SUM(order_details.quantity) as units,
                    SUM(order_details.quantity * order_details.unit_price_usd) as amount
                ')
                ->groupBy('laboratories.id', 'laboratories.name')
                ->orderByDesc('amount')
                ->limit(5)
                ->get();
        }

        return [
            'topProductsByUnits' => $topProductsByUnits,
            'topProductsByAmount' => $topProductsByAmount,
            'topLabsByUnits' => $topLabsByUnits,
            'topLabsByAmount' => $topLabsByAmount
        ];
    }

    private function getMonthlySales($userId, $month, $year)
    {
        $orders = Order::where('seller_id', $userId)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->whereIn('status', ['Completed', 'Closed'])
            ->get();

        $totalAmount = $orders->sum(function($order) {
            // Convertir a USD si tiene tasa de cambio
            return $order->usd_conversion ? $order->total_amount_usd : $order->total_amount;
        });
        
        $totalUnits = OrderDetail::whereIn('order_id', $orders->pluck('id'))
            ->sum('quantity');

        return [
            'amount' => $totalAmount,
            'units' => $totalUnits
        ];
    }

    private function getAverages($userId, $month, $year)
    {
        $orders = Order::where('seller_id', $userId)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->whereIn('status', ['Completed', 'Closed'])
            ->get();

        $totalAmount = $orders->sum(function($order) {
            return $order->usd_conversion ? $order->total_amount_usd : $order->total_amount;
        });
        
        $orderCount = $orders->count();
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        $dailyAverage = $orderCount > 0 ? $totalAmount / $daysInMonth : 0;
        $ticketAverage = $orderCount > 0 ? $totalAmount / $orderCount : 0;

        return [
            'dailyAverage' => $dailyAverage,
            'ticketAverage' => $ticketAverage
        ];
    }

    private function getTopProducts($userId, $month, $year, $limit = 5)
    {
        return OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->where('orders.seller_id', $userId)
            ->whereMonth('orders.order_date', $month)
            ->whereYear('orders.order_date', $year)
            ->where('orders.status', 'Completed')
            ->selectRaw('
                products.id,
                products.name,
                SUM(order_details.quantity) as units
            ')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('units')
            ->limit($limit)
            ->get();
    }

    private function getInventoryCounts($userId)
    {
        $activeCycleId = InventoryCycle::where('status', 'active')->value('id');

        if (!$activeCycleId) {
            return [
                'total' => 0,
                'discrepancies' => 0
            ];
        }

        // 1. Conteos desde Ventas (SaleCount)
        $saleCounts = SaleCount::where('user_id', $userId)
            ->where('cycle_id', $activeCycleId)
            ->get();
        $totalSaleCounts = $saleCounts->count();
        $saleDiscrepancies = $saleCounts->where('discrepancy', '!=', 0)->count();

        // 2. Conteos desde Facturas (InvoiceCount)
        $invoiceCounts = InvoiceCount::where('user_id', $userId)
            ->where('cycle_id', $activeCycleId)
            ->get();
        $totalInvoiceCounts = $invoiceCounts->count();
        $invoiceDiscrepancies = $invoiceCounts->where('discrepancy', '!=', 0)->count();

        // 3. Conteos Normales/Cíclicos (ProductCount)
        $productCounts = ProductCount::where('user_id', $userId)
            ->where('cycle_id', $activeCycleId)
            ->get();
        $totalProductCounts = $productCounts->count();
        $productDiscrepancies = $productCounts->where('discrepancy', '!=', 0)->count();

        return [
            'total' => $totalSaleCounts + $totalInvoiceCounts + $totalProductCounts,
            'discrepancies' => $saleDiscrepancies + $invoiceDiscrepancies + $productDiscrepancies
        ];
    }
}
