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
use App\Http\Resources\Dashboard\SoldExpiringProductResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            Log::error("ERROR in DashboardController::getStats: " . $e->getMessage());
            return response()->json([
                'units' => 0,
                'sales' => 0.0,
                'expenses' => 0.0,
                'profit' => 0.0,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUnitsSold(DashboardStatsRequest $request): UnitsSoldResource
    {
        $units = $this->orderQueryService->getUnitsSold(
            $request->validated('start_date'),
            $request->validated('end_date')
        );

        return new UnitsSoldResource($units);
    }

    public function getProfit(DashboardStatsRequest $request): ProfitResource
    {
        $profit = $this->orderQueryService->getProfit(
            $request->validated('start_date'),
            $request->validated('end_date')
        );

        return new ProfitResource($profit);
    }

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

        $transactionRepo = new \App\Repositories\TransactionRepository();

        // 1. Promedio de Ventas Diarias (Mes actual)
        $daysElapsed = $today->day;
        $totalMonthlySales = DB::table('cash_closing')
            ->whereBetween('closing_date', [$currentMonthStart->toDateString(), $currentMonthEnd->toDateString()])
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
            
            $totalMonthly = DB::table('cash_closing')
                ->whereBetween('closing_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'closed')
                ->sum('total_sales');

            $historicalAverages[] = [
                'month' => $month->translatedFormat('M'),
                'average' => $daysInPeriod > 0 ? round($totalMonthly / $daysInPeriod, 2) : 0
            ];
        }

        // 3. Métricas de Última Semana vs Semana Anterior
        $maxClosingDate = DB::table('cash_closing')->max('closing_date');
        $lastWeekEnd = $maxClosingDate ? Carbon::parse($maxClosingDate)->endOfDay() : now()->endOfDay();
        $lastWeekStart = $lastWeekEnd->copy()->subDays(6)->startOfDay();
        $prevWeekEnd = $lastWeekStart->copy()->subSecond();
        $prevWeekStart = $prevWeekEnd->copy()->subDays(6)->startOfDay();

        $getWeeklyStats = function($start, $end) {
            $sales = DB::table('cash_closing')
                ->whereBetween('closing_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'closed')
                ->sum('total_sales');

            $orders = DB::table('orders')
                ->where('status', 'Completed')
                ->whereBetween('order_date', [$start, $end])
                ->get(['total_amount_usd', 'total_cost']);
            
            $profit = 0;
            foreach ($orders as $order) {
                $profit += ((float)$order->total_amount_usd - (float)$order->total_cost);
            }

            $ordersCount = DB::table('orders')
                ->whereBetween('order_date', [$start, $end])
                ->count();

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
        $ordersStats = DB::table('orders')
            ->whereBetween('order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
            
        $completedOrders = $ordersStats['Completed'] ?? 0;
        $cancelledOrders = ($ordersStats['Cancelled'] ?? 0) + ($ordersStats['Abandoned'] ?? 0);
        $totalOrdersForSummary = $completedOrders + $cancelledOrders;

        // 5. Informe de Ganancias (Últimos 7 días)
        $dailyEarnings = [];
        $baseDate = $maxClosingDate ? Carbon::parse($maxClosingDate) : now();
        for ($i = 6; $i >= 0; $i--) {
            $date = $baseDate->copy()->subDays($i);
            $dateStr = $date->toDateString();
            
            $dayIncome = DB::table('cash_closing')
                ->where('closing_date', $dateStr)
                ->where('status', 'closed')
                ->sum('total_sales');
            
            $dayCost = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.order_date', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->sum(DB::raw('order_details.quantity * order_details.unit_cost'));

            $dailyEarnings[] = [
                'date' => $date->translatedFormat('d M'),
                'label' => $date->translatedFormat('D'),
                'sales' => round($dayIncome, 2),
                'cost' => round($dayCost, 2),
                'profit' => round($dayIncome - $dayCost, 2)
            ];
        }

        // 6. Ventas por Laboratorio (Top Monto)
        $labSummaryAmount = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
            ->select('laboratories.name', DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as total_usd'))
            ->groupBy('laboratories.name')
            ->orderByDesc('total_usd')
            ->limit(6)
            ->get()
            ->map(function($lab) {
                return [
                    'name' => $lab->name,
                    'amount' => round($lab->total_usd, 2),
                    'change_pct' => 0,
                    'is_positive' => true
                ];
            });

        // 7. Unidades por Laboratorio (Top Unidades)
        $labSummaryUnits = DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
            ->select('laboratories.name', DB::raw('SUM(order_details.quantity) as total_units'))
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

        // 8. Auto Ordenes
        $autoOrders = DB::table('auto_orders')
            ->whereMonth('order_date', now()->month)
            ->get();

        // 9. Expiraciones
        $expirations = collect(range(1, 4))->map(function($i) {
            $date = now()->addMonths($i - 1);
            $units = DB::table('product_lots')
                ->where('quantity', '>', 0)
                ->whereYear('expiration_date', $date->year)
                ->whereMonth('expiration_date', $date->month)
                ->sum('quantity');
            return [
                'month' => $date->translatedFormat('F'),
                'count' => (int)$units,
            ];
        });

        // 10. Sellers Ranking
        $sellers = DB::table('users')
            ->join('orders', 'users.id', '=', 'orders.seller_id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
            ->select('users.id', 'users.username')
            ->distinct()
            ->get()
            ->map(function($user) use ($currentMonthStart, $currentMonthEnd) {
                $stats = DB::table('order_details')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->join('products', 'order_details.product_id', '=', 'products.id')
                    ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->where('orders.seller_id', $user->id)
                    ->where('orders.status', 'Completed')
                    ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
                    ->select(
                        'laboratories.name',
                        DB::raw('SUM(order_details.quantity * order_details.unit_price_usd) as total_usd'),
                        DB::raw('SUM(order_details.quantity) as total_units')
                    )
                    ->groupBy('laboratories.name')
                    ->get();

                $topLabAmount = $stats->sortByDesc('total_usd')->first();
                $topLabUnits = $stats->sortByDesc('total_units')->first();
                
                $totalSales = DB::table('orders')
                    ->where('seller_id', $user->id)
                    ->where('status', 'Completed')
                    ->whereBetween('order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
                    ->sum('total_amount_usd');

                return [
                    'name' => $user->username,
                    'total' => round((float)$totalSales, 2),
                    'top_lab_amount' => $topLabAmount ? $topLabAmount->name : 'N/A',
                    'top_lab_units' => $topLabUnits ? $topLabUnits->name : 'N/A',
                ];
            })->sortByDesc('total')->values()->take(6);

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
            'auto_orders_summary' => [
                'pending' => $autoOrders->where('status', 0)->count(),
                'sent' => $autoOrders->where('status', 1)->count(),
                'completed' => $autoOrders->where('status', 2)->count(),
                'total' => $autoOrders->count(),
            ],
            'expirations_summary' => $expirations,
            'conversion_summary' => collect(range(1, now()->day))->map(function($i) {
                $date = now()->startOfMonth()->addDays($i - 1);
                $dateStr = $date->toDateString();
                $quotations = DB::table('quotations')->whereDate('created_at', $dateStr)->get();
                $conversions = 0;
                foreach ($quotations as $q) {
                    if (DB::table('orders')->where('client_id', $q->client_id)->whereBetween('created_at', [$q->created_at, Carbon::parse($q->created_at)->addMinutes(30)])->exists()) $conversions++;
                }
                return ['label' => $date->translatedFormat('d M'), 'quotations' => $quotations->count(), 'conversions' => $conversions];
            }),
            'promotions_summary' => collect([
                ['name' => 'Oferta Individual', 'type' => 'individual'],
                ['name' => 'Oferta Caducidad', 'type' => 'expiration'],
                ['name' => 'Oferta Recipe', 'type' => 'recipe'],
                ['name' => 'Oferta Pack', 'type' => 'pack']
            ])->map(function($p) use ($currentMonthStart, $currentMonthEnd) {
                $query = DB::table('order_details')->join('orders', 'order_details.order_id', '=', 'orders.id')->where('orders.status', 'Completed')->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()]);
                if ($p['type'] === 'pack') $query->whereNotNull('order_details.pack_id');
                else $query->where('order_details.discount_type', $p['type']);
                return ['name' => $p['name'], 'orders' => $query->distinct('order_details.order_id')->count()];
            }),
            'packs_summary' => DB::table('product_packs')->where('is_active', true)->get()->map(function($pack) use ($currentMonthStart, $currentMonthEnd) {
                return ['name' => $pack->name, 'units' => DB::table('order_details')->join('orders', 'order_details.order_id', '=', 'orders.id')->where('order_details.pack_id', $pack->id)->where('orders.status', 'Completed')->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])->count()];
            })->sortByDesc('units')->values(),
            'sellers_ranking' => $sellers,
            'exchange_rates' => DB::table('exchange_rates')->get()->map(fn($r) => ['id' => $r->id ?? 0, 'currency' => $r->currency_code, 'rate' => round($r->rate, 2)]),
            'system_profitability' => 25.2
        ]);
    }

    public function getEmployeeSalesByAmount(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        return response()->json(['data' => $this->orderQueryService->getEmployeeSalesByAmount($year)]);
    }

    public function getEmployeeSalesByUnits(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        return response()->json(['data' => $this->orderQueryService->getEmployeeSalesByUnits($year)]);
    }

    public function getSoldExpiringProducts(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $movements = app(\App\Services\Expirations\ExpirationQueryService::class)->getSoldExpiringLotsThisMonth();
        return SoldExpiringProductResource::collection($movements);
    }

    public function getMinimarketStats(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $cacheKey = "minimarket_stats_{$startDate}_{$endDate}";

        $statsData = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($startDate, $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // 1. Métricas generales
            $posSales = (float) DB::table('orders')
                ->where('status', 'Completed')
                ->whereBetween('order_date', [$start, $end])
                ->sum('total_amount_usd');

            $webSales = (float) DB::table('ecommerce_orders')
                ->whereIn('status', ['Paid', 'Shipped', 'Delivered'])
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_amount');

            $totalSales = $posSales + $webSales;

            $posCost = (float) DB::table('orders')
                ->where('status', 'Completed')
                ->whereBetween('order_date', [$start, $end])
                ->sum('total_cost');

            // Para e-commerce, calculamos el costo a partir de los ítems de las órdenes
            $webCost = (float) DB::table('ecommerce_order_items')
                ->join('ecommerce_orders', 'ecommerce_orders.id', '=', 'ecommerce_order_items.ecommerce_order_id')
                ->join('products', 'products.id', '=', 'ecommerce_order_items.product_id')
                ->whereIn('ecommerce_orders.status', ['Paid', 'Shipped', 'Delivered'])
                ->whereBetween('ecommerce_orders.created_at', [$start, $end])
                ->sum(DB::raw('ecommerce_order_items.quantity * products.unit_cost'));

            $totalProfit = ($posSales - $posCost) + ($webSales - $webCost);

            // Cantidad de transacciones
            $posTransactions = DB::table('orders')
                ->where('status', 'Completed')
                ->whereBetween('order_date', [$start, $end])
                ->count();

            $webTransactions = DB::table('ecommerce_orders')
                ->whereIn('status', ['Paid', 'Shipped', 'Delivered'])
                ->whereBetween('created_at', [$start, $end])
                ->count();

            // 2. Métodos de Pago
            // Obtener pagos de POS
            $posOrders = DB::table('orders')
                ->where('status', 'Completed')
                ->whereBetween('order_date', [$start, $end])
                ->get(['payment_methods']);

            $payments = [
                'Efectivo' => 0.0,
                'Pago Móvil' => 0.0,
                'Zelle' => 0.0,
                'Tarjeta / Puntos' => 0.0,
                'Otros' => 0.0
            ];

            foreach ($posOrders as $order) {
                $methods = json_decode($order->payment_methods, true);
                if (is_array($methods)) {
                    foreach ($methods as $method) {
                        $name = mb_strtoupper($method['method'] ?? '', 'UTF-8');
                        $amount = (float) ($method['amount_usd'] ?? $method['amount'] ?? 0);
                        if (str_contains($name, 'EFECTIVO') || str_contains($name, 'CASH')) {
                            $payments['Efectivo'] += $amount;
                        } elseif (str_contains($name, 'MOVIL') || str_contains($name, 'PAGO')) {
                            $payments['Pago Móvil'] += $amount;
                        } elseif (str_contains($name, 'ZELLE')) {
                            $payments['Zelle'] += $amount;
                        } elseif (str_contains($name, 'PUNTO') || str_contains($name, 'TARJETA') || str_contains($name, 'DEBITO') || str_contains($name, 'CREDITO')) {
                            $payments['Tarjeta / Puntos'] += $amount;
                        } else {
                            $payments['Otros'] += $amount;
                        }
                    }
                }
            }

            // Sumar pagos de e-commerce
            $webOrders = DB::table('ecommerce_orders')
                ->whereIn('status', ['Paid', 'Shipped', 'Delivered'])
                ->whereBetween('created_at', [$start, $end])
                ->get(['payment_method', 'total_amount']);

            foreach ($webOrders as $wOrder) {
                $name = mb_strtoupper($wOrder->payment_method ?? '', 'UTF-8');
                $amount = (float) $wOrder->total_amount;
                if (str_contains($name, 'EFECTIVO') || str_contains($name, 'CASH')) {
                    $payments['Efectivo'] += $amount;
                } elseif (str_contains($name, 'MOVIL') || str_contains($name, 'PAGO')) {
                    $payments['Pago Móvil'] += $amount;
                } elseif (str_contains($name, 'ZELLE')) {
                    $payments['Zelle'] += $amount;
                } else {
                    $payments['Tarjeta / Puntos'] += $amount;
                }
            }

            // Convertir a estructura de gráfica
            $paymentDistribution = [];
            foreach ($payments as $label => $val) {
                if ($val > 0) {
                    $paymentDistribution[] = ['label' => $label, 'value' => round($val, 2)];
                }
            }

            // 3. Ventas por Categoría (POS + Web)
            $posCatSales = DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->join('products', 'products.id', '=', 'order_details.product_id')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.order_date', [$start, $end])
                ->selectRaw('categories.name as category_name, SUM(order_details.quantity * order_details.price) as sales')
                ->groupBy('categories.name')
                ->get();

            $webCatSales = DB::table('ecommerce_order_items')
                ->join('ecommerce_orders', 'ecommerce_orders.id', '=', 'ecommerce_order_items.ecommerce_order_id')
                ->join('products', 'products.id', '=', 'ecommerce_order_items.product_id')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->whereIn('ecommerce_orders.status', ['Paid', 'Shipped', 'Delivered'])
                ->whereBetween('ecommerce_orders.created_at', [$start, $end])
                ->selectRaw('categories.name as category_name, SUM(ecommerce_order_items.quantity * ecommerce_order_items.price) as sales')
                ->groupBy('categories.name')
                ->get();

            $categoriesCombined = [];
            foreach ($posCatSales as $cs) {
                $categoriesCombined[$cs->category_name] = (float) $cs->sales;
            }
            foreach ($webCatSales as $cs) {
                $categoriesCombined[$cs->category_name] = ($categoriesCombined[$cs->category_name] ?? 0.0) + (float) $cs->sales;
            }

            $categorySalesFormatted = [];
            foreach ($categoriesCombined as $catName => $salesSum) {
                $categorySalesFormatted[] = [
                    'name' => $catName,
                    'value' => round($salesSum, 2)
                ];
            }
            usort($categorySalesFormatted, fn($a, $b) => $b['value'] <=> $a['value']);
            $categorySalesFormatted = array_slice($categorySalesFormatted, 0, 5);

            // 4. Alertas de Stock Bajo
            $lowStock = \App\Models\Product::with('category')
                ->where('stock', '<', 10)
                ->orderBy('stock', 'asc')
                ->limit(6)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'stock' => (float)$p->stock,
                    'category' => $p->category?->name ?? 'GENERAL',
                    'supplier_id' => $p->supplier_id
                ]);

            // 5. Últimas órdenes del E-commerce
            $recentWebOrders = DB::table('ecommerce_orders')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get()
                ->map(fn($o) => [
                    'id' => $o->id,
                    'customer_name' => $o->customer_name,
                    'total_amount' => (float)$o->total_amount,
                    'status' => $o->status,
                    'payment_method' => $o->payment_method,
                    'created_at' => $o->created_at
                ]);

            return [
                'general_stats' => [
                    'total_sales' => round($totalSales, 2),
                    'pos_sales' => round($posSales, 2),
                    'web_sales' => round($webSales, 2),
                    'total_profit' => round($totalProfit, 2),
                    'pos_transactions' => $posTransactions,
                    'web_transactions' => $webTransactions,
                    'total_transactions' => $posTransactions + $webTransactions
                ],
                'payment_distribution' => $paymentDistribution,
                'category_sales' => $categorySalesFormatted,
                'low_stock' => $lowStock,
                'recent_web_orders' => $recentWebOrders
            ];
        });

        return response()->json($statsData);
    }
}
