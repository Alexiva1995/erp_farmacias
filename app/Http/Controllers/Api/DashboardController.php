<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\FiscalHistory;
use App\Services\Islr\IslrQueryService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Requests\Dashboard\DashboardStatsRequest;
use App\Http\Requests\Dashboard\PopularProductsRequest;
use App\Http\Resources\Dashboard\UnitsSoldResource;
use App\Http\Resources\Dashboard\ProfitResource;
use App\Http\Resources\Dashboard\PopularProductResource;


class DashboardController extends Controller
{
    public function __construct(
        private IslrQueryService $islrQueryService,
        private \App\Services\Order\OrderQueryService $orderQueryService
    ) {
    }

    public function getTotalIncome(Request $request)
    {
        $year = $request->input('year', now()->year);
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $fiscalHistory = FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])->get();

        $totalIncome = $fiscalHistory->sum('total_amount');
        $exemptAmount = $fiscalHistory->sum('exempt_amount');
        $taxableAmount = $fiscalHistory->sum('taxable_amount');

        $taxablePercentage = $totalIncome > 0 ? ($taxableAmount / $totalIncome) * 100 : 0;
        $exemptPercentage = $totalIncome > 0 ? ($exemptAmount / $totalIncome) * 100 : 0;

        return response()->json([
            'data' => [
                'total_income' => $totalIncome,
                'exempt_amount' => $exemptAmount,
                'taxable_amount' => $taxableAmount,
                'taxable_percentage' => $taxablePercentage,
                'exempt_percentage' => $exemptPercentage,
            ]
        ]);
    }

    public function getDeductibleExpenses(Request $request)
    {
        $year = $request->input('year', now()->year);
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $expensesQuery = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereIn('currency', ['BS', 'VES'])
            ->whereBetween('expense_date', [$startDate, $endDate]);

        $expenses = $expensesQuery->with('category')->get();

        $categories = $expenses->groupBy('category_id')->map(function ($group) {
            return [
                'category_id' => $group->first()->category_id,
                'category_name' => $group->first()->category?->name ?? 'Sin Categoría',
                'total_amount' => $group->sum('amount'),
            ];
        })->values();

        return response()->json([
            'data' => [
                'total_deductible' => $expenses->sum('amount'),
                'categories' => $categories
            ]
        ]);
    }

    public function getNonDeductibleExpenses(Request $request)
    {
        $year = $request->input('year', now()->year);
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $expensesQuery = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', false)
            ->whereIn('currency', ['BS', 'VES'])
            ->whereBetween('expense_date', [$startDate, $endDate]);

        $expenses = $expensesQuery->with('category')->get();

        $categories = $expenses->groupBy('category_id')->map(function ($group) {
            return [
                'category_id' => $group->first()->category_id,
                'category_name' => $group->first()->category?->name ?? 'Sin Categoría',
                'total_amount' => $group->sum('amount'),
            ];
        })->values();

        return response()->json([
            'data' => [
                'total_non_deductible' => $expenses->sum('amount'),
                'categories' => $categories
            ]
        ]);
    }

    public function getRevenueReport(Request $request)
    {
        $year = $request->input('year', now()->year);
        $monthsEn = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        // Obtener los cierres de caja diarios consolidados del año seleccionado
        $dailyClosures = \App\Models\DailyCashClosure::whereYear('created_at', $year)
            ->get(['created_at', 'total_sales']);

        // Obtener los gastos aprobados del año seleccionado
        $expenses = \App\Models\Expense::where('status', \App\Models\Expense::STATUS_APPROVED)
            ->whereYear('expense_date', $year)
            ->get(['expense_date', 'total_usd']);

        // Obtener pedidos completados del año agrupados por mes
        $ordersRaw = \App\Models\Order::where('status', 'Completed')
            ->whereYear('order_date', $year)
            ->selectRaw('MONTH(order_date) as month_num, COUNT(*) as orders_count, SUM(total_amount_usd) as sales_usd, SUM(total_cost) as cost_usd')
            ->groupBy('month_num')
            ->get()
            ->keyBy('month_num');

        $monthlyData = [];

        for ($i = 1; $i <= 12; $i++) {
            // Filtrar cierres de caja del mes
            $monthClosures = $dailyClosures->filter(function ($closure) use ($i) {
                return $closure->created_at->month === $i;
            });
            $income = $monthClosures->sum('total_sales');

            // Filtrar gastos del mes
            $monthExpenses = $expenses->filter(function ($expense) use ($i) {
                return $expense->expense_date->month === $i;
            });
            $expensesTotal = $monthExpenses->sum('total_usd');

            // Pedidos y ganancia real del mes
            $monthOrders   = $ordersRaw->get($i);
            $ordersCount   = $monthOrders ? (int) $monthOrders->orders_count : 0;
            $salesUsd      = $monthOrders ? (float) $monthOrders->sales_usd  : 0.0;
            $costUsd       = $monthOrders ? (float) $monthOrders->cost_usd   : 0.0;
            $profit        = round($salesUsd - $costUsd, 2);

            $monthlyData[] = [
                'month'      => $i,
                'month_name' => $monthsEn[$i - 1],
                'income'     => round((float) $income, 2),
                'expenses'   => round((float) $expensesTotal, 2),
                'net'        => round((float) ($income - $expensesTotal), 2),
                'orders'     => $ordersCount,
                'sales'      => round($salesUsd, 2),
                'profit'     => $profit,
            ];
        }

        $transformedMonthlyData = collect($monthlyData);

        $summary = [
            'total_income'    => round($transformedMonthlyData->sum('income'), 2),
            'total_expenses'  => round($transformedMonthlyData->sum('expenses'), 2),
            'net_revenue'     => round($transformedMonthlyData->sum('net'), 2),
            'total_orders'    => $transformedMonthlyData->sum('orders'),
            'total_sales'     => round($transformedMonthlyData->sum('sales'), 2),
            'total_profit'    => round($transformedMonthlyData->sum('profit'), 2),
            'year'            => $year,
        ];

        return response()->json([
            'data' => [
                'monthly_data' => $transformedMonthlyData,
                'summary'      => $summary,
            ]
        ]);
    }

    public function getClientStats(Request $request)
    {
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        $prevMonthStart = now()->subMonth()->startOfMonth();
        $prevMonthEnd = now()->subMonth()->endOfMonth();

        // 1. Clientes compradores únicos del mes actual
        $currentBuyerIds = \App\Models\Order::whereIn('status', [\App\Models\Order::COMPLETED, \App\Models\Order::CLOSED])
            ->whereBetween('order_date', [$currentMonthStart, $currentMonthEnd])
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id');

        $currentBuyersCount = $currentBuyerIds->count();

        // 2. Clientes compradores únicos del mes anterior
        $prevBuyerIds = \App\Models\Order::whereIn('status', [\App\Models\Order::COMPLETED, \App\Models\Order::CLOSED])
            ->whereBetween('order_date', [$prevMonthStart, $prevMonthEnd])
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id');

        $prevBuyersCount = $prevBuyerIds->count();

        // 3. Desglose de tipos de cliente de los compradores del mes actual
        $buyers = \App\Models\Client::whereIn('id', $currentBuyerIds)->get(['client_type', 'created_at']);

        // Definimos los tipos de clientes a reportar
        $types = [
            'Nuevo' => 0,
            'Ocasional' => 0,
            'Frecuente' => 0,
            'VIP' => 0,
            'En Riesgo' => 0,
            'Inactivo' => 0,
        ];

        foreach ($buyers as $buyer) {
            // Si el cliente se registró en el mes actual, se considera 'Nuevo' obligatoriamente para este reporte
            $isNewThisMonth = $buyer->created_at && $buyer->created_at->between($currentMonthStart, $currentMonthEnd);
            
            $type = $isNewThisMonth ? 'Nuevo' : ($buyer->client_type ?: 'Ocasional');
            
            if (array_key_exists($type, $types)) {
                $types[$type]++;
            } else {
                $types['Ocasional']++;
            }
        }

        // Calcular variación porcentual
        $changePct = 0.0;
        if ($prevBuyersCount > 0) {
            $changePct = (($currentBuyersCount - $prevBuyersCount) / $prevBuyersCount) * 100;
        } elseif ($currentBuyersCount > 0) {
            $changePct = 100.0;
        }

        return response()->json([
            'data' => [
                'total_buyers' => $currentBuyersCount,
                'prev_buyers' => $prevBuyersCount,
                'change_pct' => round($changePct, 1),
                'is_positive' => $changePct >= 0,
                'types' => $types
            ]
        ]);
    }

    public function getStats(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
            
            $stats = $this->orderQueryService->getMonthlyStats($startDate, $endDate);
            
            return response()->json($stats);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("ERROR in DashboardController::getStats: " . $e->getMessage());
            return response()->json([
                'units' => 0,
                'sales' => 0.0,
                'expenses' => 0.0,
                'profit' => 0.0,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el total de unidades vendidas en un rango de fechas.
     *
     * @param DashboardStatsRequest $request
     * @return UnitsSoldResource
     */
    public function getUnitsSold(DashboardStatsRequest $request): UnitsSoldResource
    {
        $units = $this->orderQueryService->getUnitsSold(
            $request->validated('start_date'),
            $request->validated('end_date')
        );

        return new UnitsSoldResource($units);
    }

    /**
     * Obtiene la ganancia neta en USD en un rango de fechas.
     *
     * @param DashboardStatsRequest $request
     * @return ProfitResource
     */
    public function getProfit(DashboardStatsRequest $request): ProfitResource
    {
        $profit = $this->orderQueryService->getProfit(
            $request->validated('start_date'),
            $request->validated('end_date')
        );

        return new ProfitResource($profit);
    }

    /**
     * Obtiene los productos más vendidos en unidades en lo que va del mes.
     *
     * @param PopularProductsRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getPopularProducts(PopularProductsRequest $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $limit = $request->validated('limit', 5) ?? 5;
        $products = $this->orderQueryService->getPopularProducts($limit);

        return PopularProductResource::collection($products);
    }

    public function getAnalyticsData(Request $request)
    {
        \Carbon\Carbon::setLocale('es');
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        $today = now();

        $transactionRepo = new \App\Repository\TransactionRepository();

        // 1. Promedio de Ventas Diarias (Mes actual)
        $daysElapsed = $today->day;
        $totalMonthlySales = \App\Models\CashClosing::whereBetween('closing_date', [$currentMonthStart->toDateString(), $currentMonthEnd->toDateString()])
            ->where('status', 'closed')
            ->sum('total_sales');
        
        $averageDailySales = $daysElapsed > 0 ? $totalMonthlySales / $daysElapsed : 0;

        // 2. Histórico de Promedios Diarios (Últimos 6 meses)
        $historicalAverages = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $daysInPeriod = $month->isCurrentMonth() ? $today->day : $month->daysInMonth;
            
            $totalMonthly = \App\Models\CashClosing::whereBetween('closing_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'closed')
                ->sum('total_sales');

            $historicalAverages[] = [
                'month' => $month->translatedFormat('M'),
                'average' => $daysInPeriod > 0 ? round($totalMonthly / $daysInPeriod, 2) : 0
            ];
        }

        // 3. Métricas de Última Semana vs Semana Anterior
        $lastWeekStart = now()->subDays(6)->startOfDay();
        $lastWeekEnd = now()->endOfDay();
        $prevWeekStart = now()->subDays(13)->startOfDay();
        $prevWeekEnd = now()->subDays(7)->endOfDay();

        $getWeeklyStats = function($start, $end) use ($transactionRepo) {
            $sales = \App\Models\CashClosing::whereBetween('closing_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'closed')
                ->sum('total_sales');

            // Ganancia (Venta - Costo)
            $orders = \App\Models\Order::where('status', 'Completed')
                ->whereBetween('order_date', [$start, $end])
                ->get(['total_amount', 'total_cost', 'currency', 'total_amount_usd']);
            
            $profit = 0;
            foreach ($orders as $order) {
                $amountUsd = $order->total_amount_usd ?: 0; // Asumiendo que total_amount_usd está poblado o requiere conversión
                $profit += ($amountUsd - (float)$order->total_cost);
            }

            $ordersCount = \App\Models\Order::whereBetween('order_date', [$start, $end])->count();

            return [
                'sales' => (float)$sales,
                'profit' => (float)$profit,
                'orders' => $ordersCount
            ];
        };

        $currentWeek = $getWeeklyStats($lastWeekStart, $lastWeekEnd);
        $prevWeek = $getWeeklyStats($prevWeekStart, $prevWeekEnd);

        $calculateChange = function($curr, $prev) {
            if ($prev <= 0) return $curr > 0 ? 100 : 0;
            return round((($curr - $prev) / $prev) * 100, 1);
        };

        // 4. Resumen de Ventas (Pedidos Completados vs Cancelados/Abandonados)
        $ordersStats = \App\Models\Order::whereBetween('order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
            ->select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
            
        $completedOrders = $ordersStats['Completed'] ?? 0;
        $cancelledOrders = ($ordersStats['Cancelled'] ?? 0) + ($ordersStats['Abandoned'] ?? 0);
        $totalOrdersForSummary = $completedOrders + $cancelledOrders;

        // 5. Informe de Ganancias (Últimos 7 días)
        $dailyEarnings = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->toDateString();
            
            $dayIncome = \App\Models\CashClosing::where('closing_date', $dateStr)
                ->where('status', 'closed')
                ->sum('total_sales');
            
            $dayCost = \DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.order_date', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->sum(\DB::raw('order_details.quantity * order_details.unit_cost'));

            $dailyEarnings[] = [
                'date' => $date->translatedFormat('d M'),
                'label' => $date->translatedFormat('D'),
                'sales' => round($dayIncome, 2),
                'cost' => round($dayCost, 2),
                'profit' => round($dayIncome - $dayCost, 2)
            ];
        }

        // 6. Ventas por Laboratorio (Top Monto)
        $labSummaryAmount = \DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
            ->select('laboratories.name', \DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as total_usd'))
            ->groupBy('laboratories.name')
            ->orderByDesc('total_usd')
            ->limit(6)
            ->get()
            ->map(function($lab) use ($currentMonthStart, $currentMonthEnd) {
                // Simplificado: por ahora 100% si no hay datos previos para no sobrecargar
                return [
                    'name' => $lab->name,
                    'amount' => round($lab->total_usd, 2),
                    'change_pct' => 0,
                    'is_positive' => true
                ];
            });

        // 7. Unidades por Laboratorio (Top Unidades - Independiente de Monto)
        $labSummaryUnits = \DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
            ->select('laboratories.name', \DB::raw('SUM(order_details.quantity) as total_units'))
            ->groupBy('laboratories.name')
            ->orderByDesc('total_units')
            ->limit(6)
            ->get()
            ->map(function($lab) {
                return [
                    'name' => $lab->name,
                    'units' => (int)$lab->total_units,
                    'change_pct' => 0,
                    'is_positive' => true
                ];
            });

        return response()->json([
            'average_daily_sales' => round($averageDailySales, 2),
            'historical_averages' => $historicalAverages,
            'total_monthly_sales' => round($totalMonthlySales, 2),
            'weekly_metrics' => [
                'sales' => [
                    'value' => round($currentWeek['sales'], 2),
                    'change' => $calculateChange($currentWeek['sales'], $prevWeek['sales'])
                ],
                'profit' => [
                    'value' => round($currentWeek['profit'], 2),
                    'change' => $calculateChange($currentWeek['profit'], $prevWeek['profit'])
                ],
                'orders' => [
                    'value' => $currentWeek['orders'],
                    'change' => $calculateChange($currentWeek['orders'], $prevWeek['orders'])
                ],
            ],
            'orders_summary' => [
                'completed' => $completedOrders,
                'cancelled' => $cancelledOrders,
                'total' => $totalOrdersForSummary,
                'stats' => $ordersStats,
                'completed_pct' => $totalOrdersForSummary > 0 ? round(($completedOrders / $totalOrdersForSummary) * 100, 1) : 0,
                'cancelled_pct' => $totalOrdersForSummary > 0 ? round(($cancelledOrders / $totalOrdersForSummary) * 100, 1) : 0,
            ],
            'daily_earnings' => $dailyEarnings,
            'lab_summary_amount' => $labSummaryAmount,
            'lab_summary_units' => $labSummaryUnits,
        ]);
    }

    public function getEmployeeSalesByAmount(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $data = $this->orderQueryService->getEmployeeSalesByAmount($year);
        
        return response()->json([
            'data' => $data
        ]);
    }

    public function getEmployeeSalesByUnits(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $data = $this->orderQueryService->getEmployeeSalesByUnits($year);

        return response()->json([
            'data' => $data
        ]);
    }
}
