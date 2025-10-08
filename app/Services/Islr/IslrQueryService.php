<?php

namespace App\Services\Islr;

use App\Models\Expense;
use App\Models\FiscalHistory;
use App\Models\TaxUnit;
use Carbon\Carbon;

class IslrQueryService
{
    /**
     * Calcula la renta bruta anual: 
     * Total Fiscal - Expenses con IVA - Expenses con is_deductible
     * 
     * @param int $year Año a calcular (por defecto año actual)
     * @return float Total de renta bruta en BS
     */
    public function calculateGrossIncome(int $year): float
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $fiscalTotal = FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('total_amount');

        $expensesWithIva = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('has_invoice', true)
            ->where('is_deductible', null)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $totalExpensesIva = 0;
        foreach ($expensesWithIva as $expense) {
            $totalExpensesIva += $this->convertToBolivares($expense->amount, $expense->currency);
        }

        $expensesDeductible = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $totalExpensesDeductible = 0;
        foreach ($expensesDeductible as $expense) {
            $totalExpensesDeductible += $this->convertToBolivares($expense->amount, $expense->currency);
        }

        return (float) ($fiscalTotal - $totalExpensesIva - $totalExpensesDeductible);
    }

    /**
     * Calcula las deducciones: Expenses aprobados con is_deductible = true
     * Convierte todas las monedas a bolívares (BS)
     * 
     * @param int $year Año a calcular (por defecto año actual)
     * @return float Total de deducciones en BS
     */
    public function calculateDeductions(int $year): float
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $expenses = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $totalDeductions = 0;
        foreach ($expenses as $expense) {
            $totalDeductions += $this->convertToBolivares($expense->amount, $expense->currency);
        }

        return (float) $totalDeductions;
    }

    /**
     * Calcula los costos: Expenses aprobados con has_invoice = true
     * Convierte todas las monedas a bolívares (BS)
     * 
     * @param int $year Año a calcular (por defecto año actual)
     * @return float Total de costos en BS
     */
    public function calculateCosts(int $year): float
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $expenses = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('has_invoice', true)
            ->where('is_deductible', null)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $totalCosts = 0;
        foreach ($expenses as $expense) {
            $totalCosts += $this->convertToBolivares($expense->amount, $expense->currency);
        }

        return (float) $totalCosts;
    }

    /**
     * Obtiene el detalle de ingresos brutos con información adicional
     * Renta Bruta = Total Fiscal - Expenses con IVA - Expenses con is_deductible
     * 
     * @param int $year Año a consultar
     * @return array
     */
    public function getGrossIncomeDetails(int $year): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $fiscalRecords = FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])
            ->orderBy('invoice_date', 'desc')
            ->get();

        $fiscalTotal = $fiscalRecords->sum('total_amount');

        $expensesWithIva = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('has_invoice', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->with('category', 'user')
            ->orderBy('expense_date', 'desc')
            ->get();

        $expensesWithIva->each(function ($expense) {
            $expense->amount_in_bs = $this->convertToBolivares($expense->amount, $expense->currency);
        });

        $totalExpensesIva = $expensesWithIva->sum('amount_in_bs');

        $expensesDeductible = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->with('category', 'user')
            ->orderBy('expense_date', 'desc')
            ->get();

        $expensesDeductible->each(function ($expense) {
            $expense->amount_in_bs = $this->convertToBolivares($expense->amount, $expense->currency);
        });

        $totalExpensesDeductible = $expensesDeductible->sum('amount_in_bs');

        $grossIncome = $fiscalTotal - $totalExpensesIva - $totalExpensesDeductible;

        return [
            'gross_income' => $grossIncome,
            'fiscal_total' => $fiscalTotal,
            'expenses_iva_total' => $totalExpensesIva,
            'expenses_deductible_total' => $totalExpensesDeductible,
            'fiscal_count' => $fiscalRecords->count(),
            'expenses_iva_count' => $expensesWithIva->count(),
            'expenses_deductible_count' => $expensesDeductible->count(),
            'fiscal_records' => $fiscalRecords,
            'expenses_iva_records' => $expensesWithIva,
            'expenses_deductible_records' => $expensesDeductible
        ];
    }

    /**
     * Obtiene el detalle de deducciones con información adicional
     * Deducciones = TODOS los Expenses con is_deductible = true
     * (Incluye los que tienen ambos: iva=true AND is_deductible=true)
     * 
     * @param int $year Año a consultar
     * @return array
     */
    public function getDeductionsDetails(int $year): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $records = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->with('category', 'user')
            ->orderBy('expense_date', 'desc')
            ->get();

        $records->each(function ($expense) {
            $expense->amount_in_bs = $this->convertToBolivares($expense->amount, $expense->currency);
        });

        $total = $records->sum('amount_in_bs');

        return [
            'total' => $total,
            'count' => $records->count(),
            'records' => $records
        ];
    }

    /**
     * Obtiene estadísticas mensuales de ingresos y deducciones
     * 
     * @param int $year Año a consultar
     * @return array
     */
    public function getMonthlyStats(int $year): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $monthlyFiscal = FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])
            ->selectRaw('MONTH(invoice_date) as month, SUM(total_amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('total', 'month')
            ->toArray();

        $expensesWithIva = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('has_invoice', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $monthlyExpensesIva = [];
        foreach ($expensesWithIva as $expense) {
            $month = Carbon::parse($expense->expense_date)->month;
            $amountInBS = $this->convertToBolivares($expense->amount, $expense->currency);

            if (!isset($monthlyExpensesIva[$month])) {
                $monthlyExpensesIva[$month] = 0;
            }
            $monthlyExpensesIva[$month] += $amountInBS;
        }

        $expensesDeductible = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $monthlyDeductions = [];
        foreach ($expensesDeductible as $expense) {
            $month = Carbon::parse($expense->expense_date)->month;
            $amountInBS = $this->convertToBolivares($expense->amount, $expense->currency);

            if (!isset($monthlyDeductions[$month])) {
                $monthlyDeductions[$month] = 0;
            }
            $monthlyDeductions[$month] += $amountInBS;
        }

        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $fiscalAmount = $monthlyFiscal[$i] ?? 0;
            $expensesIvaAmount = $monthlyExpensesIva[$i] ?? 0;
            $deductions = $monthlyDeductions[$i] ?? 0;

            $grossIncome = $fiscalAmount - $expensesIvaAmount - $deductions;

            $netIncome = $fiscalAmount - $deductions;

            $monthlyData[] = [
                'month' => $i,
                'month_name' => Carbon::create($year, $i, 1)->format('F'),
                'fiscal_total' => $fiscalAmount,
                'expenses_iva' => $expensesIvaAmount,
                'deductions' => $deductions,
                'gross_income' => $grossIncome,
                'net_income' => $netIncome
            ];
        }

        return $monthlyData;
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

        $exchangeRate = \App\Models\ExchangeRate::where('currency_code', 'BS')
            ->latest('created_at')
            ->first();

        if (!$exchangeRate) {
            \Log::warning("No se encontró tasa de cambio para {$currency}. Gasto no convertido.");
            return 0;
        }

        return $amount * $exchangeRate->rate;
    }

    /**
     * Obtiene la unidad tributaria activa actual
     * 
     * @return TaxUnit|null
     */
    public function getActiveTaxUnit(): ?TaxUnit
    {
        return TaxUnit::getActive();
    }

    /**
     * Obtiene todas las unidades tributarias (histórico)
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllTaxUnits(): \Illuminate\Database\Eloquent\Collection
    {
        return TaxUnit::orderBy('effective_date', 'desc')->get();
    }

    /**
     * Obtiene una unidad tributaria por ID
     * 
     * @param int $taxUnitId
     * @return TaxUnit|null
     */
    public function getTaxUnitById(int $taxUnitId): ?TaxUnit
    {
        return TaxUnit::find($taxUnitId);
    }

    /**
     * Verifica si existe una unidad tributaria activa
     * 
     * @return bool
     */
    public function hasActiveTaxUnit(): bool
    {
        return TaxUnit::where('is_active', true)->exists();
    }

    /**
     * Obtiene las unidades tributarias en un rango de fechas
     * 
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTaxUnitsByDateRange(string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return TaxUnit::whereBetween('effective_date', [$startDate, $endDate])
            ->orderBy('effective_date', 'desc')
            ->get();
    }
}
