<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class LoanResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     */
    public function toArray(Request $request): array
    {
        $monthlyPayment = (float) $this->monthly_payment;
        $totalInstallments = (int) $this->total_installments;
        $totalAmount = $monthlyPayment * $totalInstallments;

        // Si se cargó withSum('payments', 'amount'), lo usamos. Si no, hacemos fallback (útil para show() o tests)
        $totalPaid = isset($this->payments_sum_amount) 
            ? (float) $this->payments_sum_amount 
            : (float) $this->payments()->where('status', \App\Models\Expense::STATUS_APPROVED)->sum('amount');

        $remainingBalance = max(0.0, $totalAmount - $totalPaid);

        // Cálculos basados en fechas
        $loanDate = Carbon::parse($this->loan_date);
        $now = Carbon::now();
        
        // Calcular meses transcurridos
        $monthsPassed = max(0, (int) floor($loanDate->diffInDays($now) / 30.44));
        $installmentsPaid = min($monthsPassed, $totalInstallments);
        
        $progressPercentage = $totalInstallments > 0 
            ? round(($installmentsPaid / $totalInstallments) * 100, 2) 
            : 0.0;

        $remainingMonths = max(0, $totalInstallments - $monthsPassed);

        // Estado del préstamo
        if ($remainingBalance <= 0) {
            $status = [
                'value' => 'completed',
                'text' => 'Completado',
                'color' => 'success',
                'icon' => 'tabler-circle-check',
            ];
        } elseif ($monthsPassed >= $totalInstallments) {
            $status = [
                'value' => 'overdue',
                'text' => 'Vencido',
                'color' => 'error',
                'icon' => 'tabler-alert-circle',
            ];
        } elseif ($remainingMonths <= 3) {
            $status = [
                'value' => 'ending_soon',
                'text' => 'Por Vencer',
                'color' => 'warning',
                'icon' => 'tabler-clock-hour-4',
            ];
        } else {
            $status = [
                'value' => 'active',
                'text' => 'Activo',
                'color' => 'info',
                'icon' => 'tabler-progress',
            ];
        }

        return [
            'id' => $this->id,
            'loan_date' => $this->loan_date->format('Y-m-d'),
            'monthly_payment' => $monthlyPayment,
            'total_installments' => $totalInstallments,
            'total_amount' => $totalAmount,
            'remaining_balance' => $remainingBalance,
            'months_passed' => $monthsPassed,
            'remaining_months' => $remainingMonths,
            'progress_percentage' => $progressPercentage,
            'status' => $status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
