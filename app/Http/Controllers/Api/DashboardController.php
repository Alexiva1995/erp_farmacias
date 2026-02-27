<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\FiscalHistory;
use App\Services\Islr\IslrQueryService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private IslrQueryService $islrQueryService
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
        $monthlyData = $this->islrQueryService->getMonthlyStats($year);

        // Transformar data para el reporte
        $transformedMonthlyData = collect($monthlyData)->map(function ($item) {
            return [
                'month' => $item['month'],
                'month_name' => $item['month_name'],
                'income' => $item['fiscal_total'],
                'expenses' => $item['deductions'],
                'net' => $item['net_income']
            ];
        });

        $summary = [
            'total_income' => $transformedMonthlyData->sum('income'),
            'total_expenses' => $transformedMonthlyData->sum('expenses'),
            'net_revenue' => $transformedMonthlyData->sum('net'),
            'year' => $year
        ];

        return response()->json([
            'data' => [
                'monthly_data' => $transformedMonthlyData,
                'summary' => $summary
            ]
        ]);
    }
}
