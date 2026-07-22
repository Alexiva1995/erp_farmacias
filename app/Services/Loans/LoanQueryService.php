<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Loan;

class LoanQueryService
{
    /**
     * Calcula el saldo total pendiente de todos los préstamos activos
     * 
     * @return float
     */
    public function calculateTotalBalance(): float
    {
        $loans = Loan::withSum(['payments' => function ($q) {
            $q->where('status', \App\Models\Expense::STATUS_APPROVED);
        }], 'amount')->get();

        $totalBalance = 0.0;

        foreach ($loans as $loan) {
            $totalAmount = (float) ($loan->monthly_payment * $loan->total_installments);
            $totalPaid = (float) ($loan->payments_sum_amount ?? 0.0);
            $remaining = max(0.0, $totalAmount - $totalPaid);
            $totalBalance += $remaining;
        }

        return (float) $totalBalance;
    }

    /**
     * Obtiene el detalle del saldo de cada préstamo
     * 
     * @return array
     */
    public function getDetailedBalances(): array
    {
        $loans = Loan::withSum(['payments' => function ($q) {
            $q->where('status', \App\Models\Expense::STATUS_APPROVED);
        }], 'amount')->get();

        $details = [];

        foreach ($loans as $loan) {
            $totalAmount = (float) ($loan->monthly_payment * $loan->total_installments);
            $totalPaid = (float) ($loan->payments_sum_amount ?? 0.0);
            $remaining = max(0.0, $totalAmount - $totalPaid);

            $details[] = [
                'id' => $loan->id,
                'loan_date' => $loan->loan_date->format('Y-m-d'),
                'monthly_payment' => (float) $loan->monthly_payment,
                'total_installments' => (int) $loan->total_installments,
                'total_amount' => $totalAmount,
                'remaining_balance' => $remaining,
                'is_paid_off' => $remaining <= 0.0,
            ];
        }

        return $details;
    }

    /**
     * Calcula el total de pagos mensuales de préstamos activos
     * 
     * @return float
     */
    public function calculateMonthlyPayments(): float
    {
        return (float) Loan::whereRaw('? < DATE_ADD(loan_date, INTERVAL total_installments MONTH)', [now()])
            ->sum('monthly_payment');
    }

    /**
     * Obtiene estadísticas generales de los préstamos
     * 
     * @return array
     */
    public function getLoansStats(): array
    {
        $loans = Loan::withSum(['payments' => function ($q) {
            $q->where('status', \App\Models\Expense::STATUS_APPROVED);
        }], 'amount')->get();

        $activeLoansCount = 0;
        $paidOffLoansCount = 0;
        $totalBalance = 0.0;
        $totalOriginalAmount = 0.0;

        foreach ($loans as $loan) {
            $totalAmount = (float) ($loan->monthly_payment * $loan->total_installments);
            $totalPaid = (float) ($loan->payments_sum_amount ?? 0.0);
            $remaining = max(0.0, $totalAmount - $totalPaid);

            if ($remaining <= 0.0) {
                $paidOffLoansCount++;
            } else {
                $activeLoansCount++;
            }

            $totalBalance += $remaining;
            $totalOriginalAmount += $totalAmount;
        }

        return [
            'total_loans' => $loans->count(),
            'active_loans' => $activeLoansCount,
            'paid_off_loans' => $paidOffLoansCount,
            'total_balance' => (float) $totalBalance,
            'monthly_payments' => $this->calculateMonthlyPayments(),
            'total_original_amount' => (float) $totalOriginalAmount,
        ];
    }

    /**
     * Obtiene una consulta filtrada de préstamos
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getFilteredQuery($request)
    {
        $query = Loan::query()->withSum(['payments' => function ($q) {
            $q->where('status', \App\Models\Expense::STATUS_APPROVED);
        }], 'amount');

        // Filtro por año del préstamo
        if ($request->filled('loanYear')) {
            $query->whereYear('loan_date', $request->input('loanYear'));
        }

        // Filtro por estado del préstamo
        if ($request->filled('status')) {
            $status = $request->input('status');

            switch ($status) {
                case 'active':
                    $query->whereRaw('DATEDIFF(CURDATE(), loan_date) / 30.44 < total_installments')
                        ->whereRaw('(monthly_payment * (total_installments - FLOOR(DATEDIFF(CURDATE(), loan_date) / 30.44))) > 0');
                    break;
                case 'ending_soon':
                    $query->whereRaw('(total_installments - FLOOR(DATEDIFF(CURDATE(), loan_date) / 30.44)) BETWEEN 1 AND 3')
                        ->whereRaw('(monthly_payment * (total_installments - FLOOR(DATEDIFF(CURDATE(), loan_date) / 30.44))) > 0');
                    break;
                case 'overdue':
                    $query->whereRaw('FLOOR(DATEDIFF(CURDATE(), loan_date) / 30.44) >= total_installments')
                        ->whereRaw('(monthly_payment * (total_installments - FLOOR(DATEDIFF(CURDATE(), loan_date) / 30.44))) > 0');
                    break;
                case 'completed':
                    $query->whereRaw('(monthly_payment * (total_installments - FLOOR(DATEDIFF(CURDATE(), loan_date) / 30.44))) <= 0');
                    break;
            }
        }

        // Filtro por rango de fechas
        if ($request->filled('startDate')) {
            $query->whereDate('loan_date', '>=', $request->input('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('loan_date', '<=', $request->input('endDate'));
        }

        // Ordenamiento
        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            $sortBy = $request->input('sortBy');
            $orderBy = $request->input('orderBy');

            // Ordenamientos especiales que requieren cálculos
            if ($sortBy === 'remaining_balance') {
                $query->orderByRaw("(monthly_payment * GREATEST(0, total_installments - FLOOR(DATEDIFF(CURDATE(), loan_date) / 30.44))) {$orderBy}");
            } elseif ($sortBy === 'total_amount') {
                $query->orderByRaw("(monthly_payment * total_installments) {$orderBy}");
            } else {
                // Ordenamientos normales
                $query->orderBy($sortBy, $orderBy);
            }
        } else {
            // Ordenamiento por defecto
            $query->orderBy('loan_date', 'desc');
        }

        return $query;
    }
}
