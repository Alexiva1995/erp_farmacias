<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\FiscalHistory;
use App\Models\Expense;
use App\Models\Order;
use App\Models\DailyCashClosure;

class DashboardQueryService
{
    public function getTotalIncomeData(int $year): array
    {
        return Cache::remember("dashboard_total_income_{$year}", 60, function () use ($year) {
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfDay();

            $sums = FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])
                ->selectRaw('SUM(total_amount) as total, SUM(exempt_amount) as exempt, SUM(taxable_amount) as taxable')
                ->first();

            $totalIncome = (float) ($sums->total ?? 0);
            $exemptAmount = (float) ($sums->exempt ?? 0);
            $taxableAmount = (float) ($sums->taxable ?? 0);

            $taxablePercentage = $totalIncome > 0 ? ($taxableAmount / $totalIncome) * 100 : 0;
            $exemptPercentage = $totalIncome > 0 ? ($exemptAmount / $totalIncome) * 100 : 0;

            return [
                'total_income' => round($totalIncome, 2),
                'exempt_amount' => round($exemptAmount, 2),
                'taxable_amount' => round($taxableAmount, 2),
                'taxable_percentage' => round($taxablePercentage, 2),
                'exempt_percentage' => round($exemptPercentage, 2),
            ];
        });
    }

    public function getDeductibleExpensesData(int $year, bool $isDeductible): array
    {
        $cacheKey = "dashboard_expenses_{$year}_" . ($isDeductible ? 'deductible' : 'nondeductible');
        return Cache::remember($cacheKey, 60, function () use ($year, $isDeductible) {
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfDay();

            $categories = Expense::where('status', Expense::STATUS_APPROVED)
                ->where('is_deductible', $isDeductible)
                ->whereIn('currency', ['BS', 'VES'])
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
                ->selectRaw('expenses.category_id, expense_categories.name as category_name, SUM(expenses.amount) as total_amount')
                ->groupBy('expenses.category_id', 'expense_categories.name')
                ->get();

            $total = (float) $categories->sum('total_amount');

            return [
                'total' => round($total, 2),
                'categories' => $categories
            ];
        });
    }

    public function getRevenueReportData(int $year): array
    {
        return Cache::remember("dashboard_revenue_report_{$year}", 60, function () use ($year) {
            $monthsEn = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

            $dailyClosures = DailyCashClosure::whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month_num, SUM(total_sales) as total_sales')
                ->groupBy('month_num')
                ->pluck('total_sales', 'month_num');

            $expenses = DB::table('expenses')
                ->where('status', Expense::STATUS_APPROVED)
                ->whereYear('expense_date', $year)
                ->selectRaw('MONTH(expense_date) as month_num, SUM(total_usd) as total_usd')
                ->groupBy('month_num')
                ->pluck('total_usd', 'month_num');

            $ordersRaw = DB::table('orders')
                ->where('status', Order::COMPLETED)
                ->whereYear('order_date', $year)
                ->selectRaw('MONTH(order_date) as month_num, COUNT(*) as orders_count, SUM(total_amount_usd) as sales_usd, SUM(total_cost) as cost_usd')
                ->groupBy('month_num')
                ->get()
                ->keyBy('month_num');

            $monthlyData = [];

            for ($i = 1; $i <= 12; $i++) {
                $income = (float) ($dailyClosures->get($i) ?? 0);
                $expensesTotal = (float) ($expenses->get($i) ?? 0);

                $monthOrders = $ordersRaw->get($i);
                $ordersCount = $monthOrders ? (int) $monthOrders->orders_count : 0;
                $salesUsd = $monthOrders ? (float) $monthOrders->sales_usd : 0.0;
                $costUsd = $monthOrders ? (float) $monthOrders->cost_usd : 0.0;
                $profit = round($salesUsd - $costUsd, 2);

                $monthlyData[] = [
                    'month' => $i,
                    'month_name' => $monthsEn[$i - 1],
                    'income' => round($income, 2),
                    'expenses' => round($expensesTotal, 2),
                    'net' => round($income - $expensesTotal, 2),
                    'orders' => $ordersCount,
                    'sales' => round($salesUsd, 2),
                    'profit' => $profit,
                ];
            }

            $transformedMonthlyData = collect($monthlyData);

            $summary = [
                'total_income' => round($transformedMonthlyData->sum('income'), 2),
                'total_expenses' => round($transformedMonthlyData->sum('expenses'), 2),
                'net_revenue' => round($transformedMonthlyData->sum('net'), 2),
                'total_orders' => $transformedMonthlyData->sum('orders'),
                'total_sales' => round($transformedMonthlyData->sum('sales'), 2),
                'total_profit' => round($transformedMonthlyData->sum('profit'), 2),
                'year' => $year,
            ];

            return [
                'monthly_data' => $transformedMonthlyData,
                'summary' => $summary,
            ];
        });
    }

    public function getClientStatsData(): array
    {
        return Cache::remember("dashboard_client_stats", 60, function () {
            $currentMonthStart = now()->startOfMonth();
            $currentMonthEnd = now()->endOfMonth();
            $prevMonthStart = now()->subMonth()->startOfMonth();
            $prevMonthEnd = now()->subMonth()->endOfMonth();

            $currentBuyerIds = DB::table('orders')
                ->whereIn('status', [Order::COMPLETED, Order::CLOSED])
                ->whereBetween('order_date', [$currentMonthStart, $currentMonthEnd])
                ->whereNotNull('client_id')
                ->distinct()
                ->pluck('client_id');

            $currentBuyersCount = $currentBuyerIds->count();

            $prevBuyersCount = DB::table('orders')
                ->whereIn('status', [Order::COMPLETED, Order::CLOSED])
                ->whereBetween('order_date', [$prevMonthStart, $prevMonthEnd])
                ->whereNotNull('client_id')
                ->distinct()
                ->count('client_id');

            $types = [
                'Nuevo' => 0,
                'Ocasional' => 0,
                'Frecuente' => 0,
                'VIP' => 0,
                'En Riesgo' => 0,
                'Inactivo' => 0,
            ];

            if ($currentBuyerIds->isNotEmpty()) {
                $buyers = DB::table('clients')
                    ->whereIn('id', $currentBuyerIds)
                    ->select(['client_type', 'created_at'])
                    ->get();

                foreach ($buyers as $buyer) {
                    $createdAt = $buyer->created_at ? Carbon::parse($buyer->created_at) : null;
                    $isNewThisMonth = $createdAt && $createdAt->between($currentMonthStart, $currentMonthEnd);
                    $type = $isNewThisMonth ? 'Nuevo' : ($buyer->client_type ?: 'Ocasional');

                    if (array_key_exists($type, $types)) {
                        $types[$type]++;
                    } else {
                        $types['Ocasional']++;
                    }
                }
            }

            $changePct = 0.0;
            if ($prevBuyersCount > 0) {
                $changePct = (($currentBuyersCount - $prevBuyersCount) / $prevBuyersCount) * 100;
            } elseif ($currentBuyersCount > 0) {
                $changePct = 100.0;
            }

            return [
                'total_buyers' => $currentBuyersCount,
                'prev_buyers' => $prevBuyersCount,
                'change_pct' => round($changePct, 1),
                'is_positive' => $changePct >= 0,
                'types' => $types
            ];
        });
    }

    public function getAnalyticsDataOptimized(): array
    {
        return Cache::remember("dashboard_analytics_data_v4", 30, function () {
            Carbon::setLocale('es');
            $currentMonthStart = now()->startOfMonth();
            $currentMonthEnd = now()->endOfMonth();
            $today = now();
            $daysElapsed = $today->day;

            $totalMonthlySales = (float) DB::table('cash_closing')
                ->whereBetween('closing_date', [$currentMonthStart->toDateString(), $currentMonthEnd->toDateString()])
                ->where('status', 'closed')
                ->sum('total_sales');

            $averageDailySales = $daysElapsed > 0 ? $totalMonthlySales / $daysElapsed : 0;

            $sixMonthsAgoStart = now()->subMonths(5)->startOfMonth()->toDateString();
            $historicalMonthly = DB::table('cash_closing')
                ->whereBetween('closing_date', [$sixMonthsAgoStart, $currentMonthEnd->toDateString()])
                ->where('status', 'closed')
                ->selectRaw("DATE_FORMAT(closing_date, '%Y-%m') as ym, SUM(total_sales) as total")
                ->groupBy('ym')
                ->pluck('total', 'ym');

            $historicalAverages = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $ymKey = $month->format('Y-m');
                $daysInPeriod = $month->isCurrentMonth() ? $today->day : $month->daysInMonth;
                $totalMonthly = (float) ($historicalMonthly->get($ymKey) ?? 0);

                $historicalAverages[] = [
                    'month' => $month->translatedFormat('M'),
                    'average' => $daysInPeriod > 0 ? round($totalMonthly / $daysInPeriod, 2) : 0
                ];
            }

            $maxClosingDate = DB::table('cash_closing')->max('closing_date');
            $lastWeekEnd = $maxClosingDate ? Carbon::parse($maxClosingDate)->endOfDay() : now()->endOfDay();
            $lastWeekStart = $lastWeekEnd->copy()->subDays(6)->startOfDay();
            $prevWeekEnd = $lastWeekStart->copy()->subSecond();
            $prevWeekStart = $prevWeekEnd->copy()->subDays(6)->startOfDay();

            $getWeeklyStats = function ($start, $end) {
                $sales = (float) DB::table('cash_closing')
                    ->whereBetween('closing_date', [$start->toDateString(), $end->toDateString()])
                    ->where('status', 'closed')
                    ->sum('total_sales');

                $orderStats = DB::table('orders')
                    ->where('status', Order::COMPLETED)
                    ->whereBetween('order_date', [$start, $end])
                    ->selectRaw('COUNT(*) as cnt, SUM(total_amount_usd - total_cost) as total_profit')
                    ->first();

                return [
                    'sales' => $sales,
                    'profit' => (float) ($orderStats->total_profit ?? 0),
                    'orders' => (int) ($orderStats->cnt ?? 0)
                ];
            };

            $currentWeek = $getWeeklyStats($lastWeekStart, $lastWeekEnd);
            $prevWeek = $getWeeklyStats($prevWeekStart, $prevWeekEnd);

            $calculateChange = function ($curr, $prev) {
                if ($prev <= 0) return $curr > 0 ? 100 : 0;
                return round((($curr - $prev) / $prev) * 100, 1);
            };

            $ordersStats = DB::table('orders')
                ->whereBetween('order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $completedOrders = $ordersStats['Completed'] ?? 0;
            $cancelledOrders = ($ordersStats['Cancelled'] ?? 0) + ($ordersStats['Abandoned'] ?? 0);
            $totalOrdersForSummary = $completedOrders + $cancelledOrders;

            $baseDate = $maxClosingDate ? Carbon::parse($maxClosingDate) : now();
            $sevenDaysAgo = $baseDate->copy()->subDays(6)->startOfDay();

            $dailyIncomeMap = DB::table('cash_closing')
                ->whereBetween('closing_date', [$sevenDaysAgo->toDateString(), $baseDate->toDateString()])
                ->where('status', 'closed')
                ->selectRaw('closing_date, SUM(total_sales) as total_sales')
                ->groupBy('closing_date')
                ->pluck('total_sales', 'closing_date');

            $dailyCostMap = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('orders.status', Order::COMPLETED)
                ->whereBetween('orders.order_date', [$sevenDaysAgo, $baseDate->copy()->endOfDay()])
                ->selectRaw("DATE(orders.order_date) as order_dt, SUM(order_details.quantity * order_details.unit_cost) as total_cost")
                ->groupBy('order_dt')
                ->pluck('total_cost', 'order_dt');

            $dailyEarnings = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = $baseDate->copy()->subDays($i);
                $dateStr = $date->toDateString();
                $dayIncome = (float) ($dailyIncomeMap->get($dateStr) ?? 0);
                $dayCost = (float) ($dailyCostMap->get($dateStr) ?? 0);

                $dailyEarnings[] = [
                    'date' => $date->translatedFormat('d M'),
                    'label' => $date->translatedFormat('D'),
                    'sales' => round($dayIncome, 2),
                    'cost' => round($dayCost, 2),
                    'profit' => round($dayIncome - $dayCost, 2)
                ];
            }

            $labSummaryAmount = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                ->where('orders.status', Order::COMPLETED)
                ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
                ->selectRaw('laboratories.name, SUM(order_details.quantity * order_details.unit_price_usd) as total_usd')
                ->groupBy('laboratories.name')
                ->orderByDesc('total_usd')
                ->limit(6)
                ->get()
                ->map(fn($lab) => [
                    'name' => $lab->name,
                    'amount' => round((float)$lab->total_usd, 2),
                    'change_pct' => 0,
                    'is_positive' => true
                ]);

            $labSummaryUnits = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                ->where('orders.status', Order::COMPLETED)
                ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
                ->selectRaw('laboratories.name, SUM(order_details.quantity) as total_units')
                ->groupBy('laboratories.name')
                ->orderByDesc('total_units')
                ->limit(6)
                ->get()
                ->map(fn($lab) => [
                    'name' => $lab->name,
                    'units' => (int)$lab->total_units,
                    'change_pct' => 0,
                    'is_positive' => true
                ]);

            $autoOrders = DB::table('auto_orders')
                ->whereMonth('order_date', now()->month)
                ->select('status')
                ->get();

            $expirationsDates = DB::table('product_lots')
                ->where('quantity', '>', 0)
                ->whereBetween('expiration_date', [now()->startOfMonth()->toDateString(), now()->addMonths(3)->endOfMonth()->toDateString()])
                ->selectRaw("DATE_FORMAT(expiration_date, '%Y-%m') as ym, SUM(quantity) as total_units")
                ->groupBy('ym')
                ->pluck('total_units', 'ym');

            $expirations = collect(range(1, 4))->map(function ($i) use ($expirationsDates) {
                $date = now()->addMonths($i - 1);
                $ymKey = $date->format('Y-m');
                return [
                    'month' => $date->translatedFormat('F'),
                    'count' => (int) ($expirationsDates->get($ymKey) ?? 0),
                ];
            });

            $sellerSales = DB::table('orders')
                ->where('orders.status', Order::COMPLETED)
                ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
                ->join('users', 'users.id', '=', 'orders.seller_id')
                ->selectRaw('users.id, users.username, SUM(orders.total_amount_usd) as total_sales')
                ->groupBy('users.id', 'users.username')
                ->orderByDesc('total_sales')
                ->limit(6)
                ->get();

            $sellerIds = $sellerSales->pluck('id');

            $sellerLabStats = [];
            if ($sellerIds->isNotEmpty()) {
                $sellerLabRaw = DB::table('order_details')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->join('products', 'order_details.product_id', '=', 'products.id')
                    ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->whereIn('orders.seller_id', $sellerIds)
                    ->where('orders.status', Order::COMPLETED)
                    ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
                    ->selectRaw('orders.seller_id, laboratories.name as lab_name, SUM(order_details.quantity * order_details.unit_price_usd) as total_usd, SUM(order_details.quantity) as total_units')
                    ->groupBy('orders.seller_id', 'laboratories.name')
                    ->get();

                foreach ($sellerLabRaw as $row) {
                    $sellerLabStats[$row->seller_id][] = $row;
                }
            }

            $sellers = $sellerSales->map(function ($s) use ($sellerLabStats) {
                $labs = collect($sellerLabStats[$s->id] ?? []);
                $topLabAmount = $labs->sortByDesc('total_usd')->first();
                $topLabUnits = $labs->sortByDesc('total_units')->first();

                return [
                    'name' => $s->username,
                    'total' => round((float) $s->total_sales, 2),
                    'top_lab_amount' => $topLabAmount ? $topLabAmount->lab_name : 'N/A',
                    'top_lab_units' => $topLabUnits ? $topLabUnits->lab_name : 'N/A',
                ];
            });

            $dailyQuotations = DB::table('quotations')
                ->whereBetween('created_at', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
                ->select('client_id', 'created_at')
                ->get();

            $quotationsByDate = [];
            foreach ($dailyQuotations as $q) {
                $dtStr = Carbon::parse($q->created_at)->toDateString();
                $quotationsByDate[$dtStr][] = $q;
            }

            $conversionSummary = collect(range(1, now()->day))->map(function ($i) use ($quotationsByDate) {
                $date = now()->startOfMonth()->addDays($i - 1);
                $dateStr = $date->toDateString();
                $dayQuotes = $quotationsByDate[$dateStr] ?? [];
                $conversions = 0;

                foreach ($dayQuotes as $q) {
                    $created = Carbon::parse($q->created_at);
                    $hasOrder = DB::table('orders')
                        ->where('client_id', $q->client_id)
                        ->whereBetween('created_at', [$created, $created->copy()->addMinutes(30)])
                        ->exists();
                    if ($hasOrder) $conversions++;
                }

                return [
                    'label' => $date->translatedFormat('d M'),
                    'quotations' => count($dayQuotes),
                    'conversions' => $conversions
                ];
            });

            $promoTypes = [
                ['name' => 'Oferta Individual', 'type' => 'individual'],
                ['name' => 'Oferta Caducidad', 'type' => 'expiration'],
                ['name' => 'Oferta Recipe', 'type' => 'recipe'],
                ['name' => 'Oferta Pack', 'type' => 'pack']
            ];

            $promotionsSummary = collect($promoTypes)->map(function ($p) use ($currentMonthStart, $currentMonthEnd) {
                $query = DB::table('order_details')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->where('orders.status', Order::COMPLETED)
                    ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()]);

                if ($p['type'] === 'pack') {
                    $query->whereNotNull('order_details.pack_id');
                } else {
                    $query->where('order_details.discount_type', $p['type']);
                }

                return [
                    'name' => $p['name'],
                    'orders' => $query->distinct('order_details.order_id')->count('order_details.order_id')
                ];
            });

            $packsUnitsMap = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereNotNull('order_details.pack_id')
                ->where('orders.status', Order::COMPLETED)
                ->whereBetween('orders.order_date', [$currentMonthStart->startOfDay(), $currentMonthEnd->endOfDay()])
                ->groupBy('order_details.pack_id')
                ->pluck(DB::raw('COUNT(*)'), 'order_details.pack_id');

            $packsSummary = DB::table('product_packs')
                ->where('is_active', true)
                ->select(['id', 'name'])
                ->get()
                ->map(fn($pack) => [
                    'name' => $pack->name,
                    'units' => (int) ($packsUnitsMap->get($pack->id) ?? 0)
                ])
                ->sortByDesc('units')
                ->values();

            $exchangeRates = DB::table('exchange_rates')
                ->select(['id', 'currency_code', 'rate'])
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id ?? 0,
                    'currency' => $r->currency_code,
                    'rate' => round((float)$r->rate, 2)
                ]);

            return [
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
                'conversion_summary' => $conversionSummary,
                'promotions_summary' => $promotionsSummary,
                'packs_summary' => $packsSummary,
                'sellers_ranking' => $sellers,
                'exchange_rates' => $exchangeRates,
                'system_profitability' => 25.2
            ];
        });
    }
}
