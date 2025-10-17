<?php

namespace App\Services\Islr;

use App\Models\Expense;
use App\Models\FiscalHistory;
use App\Models\IslrDeclaration;
use App\Models\TaxUnit;
use Carbon\Carbon;

class IslrQueryService
{
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
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount_bs');

        return (float) $totalDeductions;
    }

    /**
     * Calcula los costos: Expenses aprobados con has_invoice = true
     * Utiliza el campo amount_bs directamente
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
            ->where('is_deductible', null)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount_bs');

        return (float) $totalCosts;
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
            ->orderBy('invoice_date', 'desc')
            ->get();

        $fiscalTotal = $fiscalRecords->sum('total_amount');

        $expensesWithIva = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('has_invoice', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->with('category', 'user')
            ->orderBy('expense_date', 'desc')
            ->get();

        $totalExpensesIva = $expensesWithIva->sum('amount_bs');

        $expensesDeductible = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->with('category', 'user')
            ->orderBy('expense_date', 'desc')
            ->get();

        $totalExpensesDeductible = $expensesDeductible->sum('amount_bs');

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

        $total = $records->sum('amount_bs');

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

        $monthlyExpensesIva = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('has_invoice', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('MONTH(expense_date) as month, SUM(amount_bs) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('total', 'month')
            ->toArray();

        $monthlyDeductions = Expense::where('status', Expense::STATUS_APPROVED)
            ->where('is_deductible', true)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('MONTH(expense_date) as month, SUM(amount_bs) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('total', 'month')
            ->toArray();

        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $fiscalAmount = $monthlyFiscal[$i] ?? 0;
            $expensesIvaAmount = $monthlyExpensesIva[$i] ?? 0;
            $deductions = $monthlyDeductions[$i] ?? 0;

            // Renta Bruta ahora es solo el fiscal total
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

    /**
     * Obtiene una declaración por año
     * 
     * @param int $year
     * @return IslrDeclaration|null
     */
    public function getDeclarationByYear(int $year): ?IslrDeclaration
    {
        return IslrDeclaration::forYear($year)->first();
    }

    /**
     * Obtiene la última declaración
     * 
     * @return IslrDeclaration|null
     */
    public function getLatestDeclaration(): ?IslrDeclaration
    {
        return IslrDeclaration::latest()->first();
    }

    /**
     * Verifica si existe una declaración para el año especificado
     * 
     * @param int $year
     * @return bool
     */
    public function declarationExistsForYear(int $year): bool
    {
        return IslrDeclaration::forYear($year)->exists();
    }

    /**
     * Obtiene todas las declaraciones
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllDeclarations()
    {
        return IslrDeclaration::latest()->get();
    }

    /**
     * Obtiene declaraciones pagadas
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPaidDeclarations()
    {
        return IslrDeclaration::paid()->latest()->get();
    }

    /**
     * Obtiene declaraciones no pagadas
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnpaidDeclarations()
    {
        return IslrDeclaration::unpaid()->latest()->get();
    }
}
