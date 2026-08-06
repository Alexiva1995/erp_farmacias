<?php

declare(strict_types=1);

namespace App\Services\Islr;

use App\Models\Expense;
use App\Models\FiscalHistory;
use App\Models\IslrDeclaration;
use App\Models\TaxUnit;
use Carbon\Carbon;

class IslrQueryService
{
    /**
     * Obtiene los datos consolidados del resumen de ISLR para un año específico.
     *
     * @param int $year Año a calcular
     * @return array
     */
    public function getSummaryData(int $year): array
    {
        $grossIncome = $this->calculateGrossIncome($year);
        $deductions = $this->calculateDeductions($year);
        $costs = $this->calculateCosts($year);

        $fiscalTotal = $grossIncome;
        $netIncome = $fiscalTotal - $costs - $deductions;

        return [
            'gross_income' => $grossIncome,
            'deductions' => $deductions,
            'net_income' => $netIncome,
            'ibg' => $fiscalTotal,
            'costs' => $costs,
            'year' => $year,
            'currency' => 'VES',
            'calculated_at' => now()->toISOString(),
        ];
    }

    /**
     * Calcula la renta bruta anual: 
     * Total Fiscal (sin restar nada)
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

        return (float) $fiscalTotal;
    }

    /**
     * Calcula las deducciones: Expenses aprobados con is_deductible = true
     * Utiliza el campo amount_bs directamente
     * 
     * @param int $year Año a calcular (por defecto año actual)
     * @return float Total de deducciones en BS
     */
    public function calculateDeductions(int $year): float
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $totalDeductions = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereIn('currency', ['BS', 'VES'])
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        return (float) $totalDeductions;
    }

    /**
     * Calcula los costos: Expenses aprobados con has_invoice = true
     * Utiliza agregaciones SQL para evitar cargar colecciones grandes en memoria.
     * 
     * @param int $year Año a calcular (por defecto año actual)
     * @return float Total de costos en BS
     */
    public function calculateCosts(int $year): float
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $totalCosts = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('has_invoice', true)
            ->where(function($q) {
                $q->whereNull('is_deductible')->orWhere('is_deductible', false);
            })
            ->whereIn('currency', ['BS', 'VES'])
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        $bcvRate = \App\Models\ExchangeRate::where('currency_code', 'BS')->value('rate') ?? 1;

        $invoicesTotal = \App\Models\Invoice::whereHas('supplier', function($q) {
            $q->where('name', '!=', 'INFORMAL');
        })
        ->whereBetween('created_invoice_date', [$startDate, $endDate])
        ->where('currency', 'Bs')
        ->selectRaw("SUM(CASE WHEN is_indexed = 1 AND exchange_rate > 0 THEN (total_amount / exchange_rate) * ? ELSE total_amount END) as total", [$bcvRate])
        ->value('total') ?? 0;

        return (float) ($totalCosts + $invoicesTotal);
    }

    /**
     * Obtiene el detalle de ingresos brutos con información adicional
     * Renta Bruta = Total Fiscal (sin restar nada)
     * 
     * @param int $year Año a consultar
     * @return array
     */
    public function getGrossIncomeDetails(int $year): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $fiscalRecords = FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])
            ->select(['id', 'total_amount', 'invoice_date', 'invoice_number'])
            ->orderBy('invoice_date', 'desc')
            ->get();

        $fiscalTotal = $fiscalRecords->sum('total_amount');

        $expensesWithIva = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('has_invoice', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->select(['id', 'amount', 'currency', 'category_id', 'user_id', 'expense_date'])
            ->with([
                'category:id,name',
                'user:id,username'
            ])
            ->orderBy('expense_date', 'desc')
            ->get();

        $totalExpensesIva = $expensesWithIva->whereIn('currency', ['BS', 'VES'])->sum('amount');

        $expensesDeductible = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->select(['id', 'amount', 'currency', 'category_id', 'user_id', 'expense_date'])
            ->with([
                'category:id,name',
                'user:id,username'
            ])
            ->orderBy('expense_date', 'desc')
            ->get();

        $totalExpensesDeductible = $expensesDeductible->whereIn('currency', ['BS', 'VES'])->sum('amount');

        // Renta Bruta ahora es solo el total fiscal
        $grossIncome = $fiscalTotal;

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
            ->select(['id', 'amount', 'currency', 'category_id', 'user_id', 'expense_date'])
            ->with([
                'category:id,name',
                'user:id,username'
            ])
            ->orderBy('expense_date', 'desc')
            ->get();

        $total = $records->whereIn('currency', ['BS', 'VES'])->sum('amount');

        return [
            'total' => $total,
            'count' => $records->count(),
            'records' => $records
        ];
    }

    /**
     * Obtiene estadísticas mensuales de ingresos y deducciones optimizado por SQL
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
            ->pluck('total', 'month')
            ->toArray();

        $monthlyExpensesIva = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('has_invoice', true)
            ->whereIn('currency', ['BS', 'VES'])
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('MONTH(expense_date) as month, SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $bcvRate = \App\Models\ExchangeRate::where('currency_code', 'BS')->value('rate') ?? 1;

        $monthlyInvoices = \App\Models\Invoice::whereHas('supplier', function($q) {
            $q->where('name', '!=', 'INFORMAL');
        })
        ->whereBetween('created_invoice_date', [$startDate, $endDate])
        ->where('currency', 'Bs')
        ->selectRaw("MONTH(created_invoice_date) as month, SUM(CASE WHEN is_indexed = 1 AND exchange_rate > 0 THEN (total_amount / exchange_rate) * ? ELSE total_amount END) as total", [$bcvRate])
        ->groupBy('month')
        ->pluck('total', 'month')
        ->toArray();

        foreach ($monthlyInvoices as $month => $amount) {
            $monthlyExpensesIva[$month] = ($monthlyExpensesIva[$month] ?? 0) + $amount;
        }

        $monthlyDeductions = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereIn('currency', ['BS', 'VES'])
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('MONTH(expense_date) as month, SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $fiscalAmount = $monthlyFiscal[$i] ?? 0;
            $expensesIvaAmount = $monthlyExpensesIva[$i] ?? 0;
            $deductions = $monthlyDeductions[$i] ?? 0;

            $grossIncome = $fiscalAmount;
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

    public function getActiveTaxUnit(): ?TaxUnit
    {
        return TaxUnit::getActive();
    }

    public function getAllTaxUnits(): \Illuminate\Database\Eloquent\Collection
    {
        return TaxUnit::orderBy('effective_date', 'desc')->get();
    }

    public function getTaxUnitById(int $taxUnitId): ?TaxUnit
    {
        return TaxUnit::find($taxUnitId);
    }

    public function hasActiveTaxUnit(): bool
    {
        return TaxUnit::where('is_active', true)->exists();
    }

    public function getTaxUnitsByDateRange(string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return TaxUnit::whereBetween('effective_date', [$startDate, $endDate])
            ->orderBy('effective_date', 'desc')
            ->get();
    }

    public function getDeclarationByYear(int $year): ?IslrDeclaration
    {
        return IslrDeclaration::forYear($year)->first();
    }

    public function getLatestDeclaration(): ?IslrDeclaration
    {
        return IslrDeclaration::latest()->first();
    }

    public function declarationExistsForYear(int $year): bool
    {
        return IslrDeclaration::forYear($year)->exists();
    }

    public function getAllDeclarations()
    {
        return IslrDeclaration::latest()->get();
    }

    public function getPaidDeclarations()
    {
        return IslrDeclaration::paid()->latest()->get();
    }

    public function getUnpaidDeclarations()
    {
        return IslrDeclaration::unpaid()->latest()->get();
    }
}
