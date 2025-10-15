<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExchangeRate;
use App\Models\FiscalHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Obtiene los ingresos y gastos mensuales para el gráfico de Revenue Report
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRevenueReport(Request $request)
    {
        $year = $request->input('year', now()->year);

        $monthlyData = [];

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();

            $income = FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])
                ->sum('total_amount');

            $expenses = Expense::where('has_invoice', true)
                ->where('status', Expense::STATUS_APPROVED)
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->get();

            $totalExpenses = 0;
            foreach ($expenses as $expense) {
                $totalExpenses += $this->convertToBolivares($expense->amount, $expense->currency);
            }

            $monthlyData[] = [
                'month' => $month,
                'month_name' => $startDate->format('M'),
                'income' => round($income, 2),
                'expenses' => round($totalExpenses, 2),
                'net' => round($income - $totalExpenses, 2)
            ];
        }

        $totalIncome = array_sum(array_column($monthlyData, 'income'));
        $totalExpenses = array_sum(array_column($monthlyData, 'expenses'));
        $netRevenue = $totalIncome - $totalExpenses;

        return response()->json([
            'data' => [
                'monthly_data' => $monthlyData,
                'summary' => [
                    'total_income' => round($totalIncome, 2),
                    'total_expenses' => round($totalExpenses, 2),
                    'net_revenue' => round($netRevenue, 2),
                    'year' => $year
                ]
            ],
            'message' => 'Revenue report obtenido con éxito.'
        ], 200);
    }

    /**
     * Obtiene estadísticas generales del dashboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $monthlyIncome = FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('total_amount');

        $expenses = Expense::where('has_invoice', true)
            ->where('status', Expense::STATUS_APPROVED)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $monthlyExpenses = 0;
        foreach ($expenses as $expense) {
            $monthlyExpenses += $this->convertToBolivares($expense->amount, $expense->currency);
        }

        $previousMonth = Carbon::create($year, $month, 1)->subMonth();
        $prevStartDate = $previousMonth->startOfMonth();
        $prevEndDate = $previousMonth->endOfMonth();

        $previousIncome = FiscalHistory::whereBetween('invoice_date', [$prevStartDate, $prevEndDate])
            ->sum('total_amount');

        $previousExpenses = Expense::where('has_invoice', true)
            ->where('status', Expense::STATUS_APPROVED)
            ->whereBetween('expense_date', [$prevStartDate, $prevEndDate])
            ->get();

        $prevMonthExpenses = 0;
        foreach ($previousExpenses as $expense) {
            $prevMonthExpenses += $this->convertToBolivares($expense->amount, $expense->currency);
        }

        $incomeChange = $previousIncome > 0
            ? (($monthlyIncome - $previousIncome) / $previousIncome) * 100
            : 0;

        $expenseChange = $prevMonthExpenses > 0
            ? (($monthlyExpenses - $prevMonthExpenses) / $prevMonthExpenses) * 100
            : 0;

        return response()->json([
            'data' => [
                'current_month' => [
                    'income' => round($monthlyIncome, 2),
                    'expenses' => round($monthlyExpenses, 2),
                    'net' => round($monthlyIncome - $monthlyExpenses, 2)
                ],
                'previous_month' => [
                    'income' => round($previousIncome, 2),
                    'expenses' => round($prevMonthExpenses, 2),
                    'net' => round($previousIncome - $prevMonthExpenses, 2)
                ],
                'changes' => [
                    'income_change' => round($incomeChange, 2),
                    'expense_change' => round($expenseChange, 2)
                ],
                'year' => $year,
                'month' => $month
            ],
            'message' => 'Estadísticas del dashboard obtenidas con éxito.'
        ], 200);
    }

    /**
     * Obtiene el detalle de ingresos totales (gravados y exentos)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTotalIncome(Request $request)
    {
        $year = $request->input('year', now()->year);

        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $fiscalRecords = FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])
            ->get();

        $exemptAmount = 0;
        $taxableAmount = 0;

        foreach ($fiscalRecords as $record) {
            $exemptAmount += $record->exempt_amount ?? 0;

            $taxableAmount += $record->iva_amount ?? 0;
        }

        $totalIncome = $exemptAmount + $taxableAmount;

        $exemptPercentage = $totalIncome > 0 ? ($exemptAmount / $totalIncome) * 100 : 0;
        $taxablePercentage = $totalIncome > 0 ? ($taxableAmount / $totalIncome) * 100 : 0;

        return response()->json([
            'data' => [
                'total_income' => round($totalIncome, 2),
                'exempt_amount' => round($exemptAmount, 2),
                'taxable_amount' => round($taxableAmount, 2),
                'exempt_percentage' => round($exemptPercentage, 2),
                'taxable_percentage' => round($taxablePercentage, 2),
                'year' => $year
            ],
            'message' => 'Ingresos totales obtenidos con éxito.'
        ], 200);
    }

    /**
     * Obtiene los gastos deducibles agrupados por categoría
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDeductibleExpenses(Request $request)
    {
        $year = $request->input('year', now()->year);

        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $expenses = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->with('category')
            ->get();

        $groupedExpenses = [];
        $totalDeductible = 0;

        foreach ($expenses as $expense) {
            $categoryId = $expense->category_id;
            $categoryName = $expense->category ? $expense->category->name : 'Sin Categoría';

            $amountInBS = $this->convertToBolivares($expense->amount, $expense->currency);
            $totalDeductible += $amountInBS;

            if (!isset($groupedExpenses[$categoryId])) {
                $groupedExpenses[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'total_amount' => 0,
                    'count' => 0
                ];
            }

            $groupedExpenses[$categoryId]['total_amount'] += $amountInBS;
            $groupedExpenses[$categoryId]['count']++;
        }

        $groupedExpenses = array_values($groupedExpenses);
        usort($groupedExpenses, function ($a, $b) {
            return $b['total_amount'] <=> $a['total_amount'];
        });

        foreach ($groupedExpenses as &$group) {
            $group['total_amount'] = round($group['total_amount'], 2);
        }

        return response()->json([
            'data' => [
                'total_deductible' => round($totalDeductible, 2),
                'categories' => $groupedExpenses,
                'year' => $year
            ],
            'message' => 'Gastos deducibles obtenidos con éxito.'
        ], 200);
    }

    /**
     * Obtiene los gastos no deducibles agrupados por categoría
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNonDeductibleExpenses(Request $request)
    {
        $year = $request->input('year', now()->year);

        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $expenses = Expense::where('status', Expense::STATUS_APPROVED)
            ->where(function ($query) {
                $query->where('is_deductible', false)
                    ->orWhereNull('is_deductible');
            })
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->with('category')
            ->get();

        $groupedExpenses = [];
        $totalNonDeductible = 0;

        foreach ($expenses as $expense) {
            $categoryId = $expense->category_id;
            $categoryName = $expense->category ? $expense->category->name : 'Sin Categoría';

            $amountInBS = $this->convertToBolivares($expense->amount, $expense->currency);
            $totalNonDeductible += $amountInBS;

            if (!isset($groupedExpenses[$categoryId])) {
                $groupedExpenses[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'total_amount' => 0,
                    'count' => 0
                ];
            }

            $groupedExpenses[$categoryId]['total_amount'] += $amountInBS;
            $groupedExpenses[$categoryId]['count']++;
        }

        $groupedExpenses = array_values($groupedExpenses);
        usort($groupedExpenses, function ($a, $b) {
            return $b['total_amount'] <=> $a['total_amount'];
        });

        foreach ($groupedExpenses as &$group) {
            $group['total_amount'] = round($group['total_amount'], 2);
        }

        return response()->json([
            'data' => [
                'total_non_deductible' => round($totalNonDeductible, 2),
                'categories' => $groupedExpenses,
                'year' => $year
            ],
            'message' => 'Gastos no deducibles obtenidos con éxito.'
        ], 200);
    }

    /**
     * Convierte un monto a bolívares según la moneda
     * 
     * @param float $amount Monto a convertir
     * @param string $currency Moneda del monto (BS, USD, COP)
     * @return float Monto en bolívares
     */
    private function convertToBolivares(float $amount, string $currency): float
    {
        if (strtoupper($currency) == 'BS') {
            return $amount;
        }

        $exchangeRate = ExchangeRate::where('currency_code', 'BS')
            ->latest('created_at')
            ->first();

        if (!$exchangeRate) {
            \Log::warning("No se encontró tasa de cambio para {$currency}. Gasto no convertido.");
            return 0;
        }

        return $amount * $exchangeRate->rate;
    }
}
