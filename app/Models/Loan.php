<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_date',
        'monthly_payment',
        'total_installments',
    ];

    protected $casts = [
        'loan_date' => 'date',
        'monthly_payment' => 'decimal:2',
        'total_installments' => 'integer',
    ];

    /**
     * Calcula el saldo pendiente del préstamo basado en las cuotas transcurridas
     * 
     * @return float
     */
    public function getRemainingBalance(): float
    {
        $monthsPassed = floor(Carbon::parse($this->loan_date)->diffInMonths(Carbon::now()));
        $installmentsPaid = min($monthsPassed, $this->total_installments);
        $remainingInstallments = max(0, $this->total_installments - $installmentsPaid);

        return $this->monthly_payment * $remainingInstallments;
    }

    /**
     * Calcula el monto total del préstamo
     * 
     * @return float
     */
    public function getTotalAmount(): float
    {
        return $this->monthly_payment * $this->total_installments;
    }

    /**
     * Verifica si el préstamo está completamente pagado
     * 
     * @return bool
     */
    public function isPaidOff(): bool
    {
        return $this->getRemainingBalance() <= 0;
    }
}
