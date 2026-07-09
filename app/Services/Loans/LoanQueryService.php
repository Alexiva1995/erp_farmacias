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
        $loans = Loan::all();
        $totalBalance = 0;

        foreach ($loans as $loan) {
            if (!$loan->isPaidOff()) {
                $totalBalance += $loan->getRemainingBalance();
            }
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
        $loans = Loan::all();
        $details = [];

        foreach ($loans as $loan) {
            $details[] = [
                'id' => $loan->id,
                'loan_date' => $loan->loan_date->format('Y-m-d'),
                'monthly_payment' => $loan->monthly_payment,
                'total_installments' => $loan->total_installments,
                'total_amount' => $loan->getTotalAmount(),
                'remaining_balance' => $loan->getRemainingBalance(),
                'is_paid_off' => $loan->isPaidOff(),
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
        return Loan::whereRaw('? < DATE_ADD(loan_date, INTERVAL total_installments MONTH)', [now()])
            ->sum('monthly_payment');
    }

    /**
     * Obtiene estadísticas generales de los préstamos
     * 
     * @return array
     */
    public function getLoansStats(): array
    {
        $loans = Loan::all();

        $activeLoans = $loans->filter(fn($loan) => !$loan->isPaidOff());
        $paidOffLoans = $loans->filter(fn($loan) => $loan->isPaidOff());

        return [
            'total_loans' => $loans->count(),
            'active_loans' => $activeLoans->count(),
            'paid_off_loans' => $paidOffLoans->count(),
            'total_balance' => $this->calculateTotalBalance(),
            'monthly_payments' => $this->calculateMonthlyPayments(),
            'total_original_amount' => $loans->sum(fn($loan) => $loan->getTotalAmount()),
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
        $query = Loan::query();

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
