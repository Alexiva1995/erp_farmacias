<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * Pagos realizados al préstamo (gastos vinculados)
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Calcula el saldo pendiente del préstamo basado en las cuotas transcurridas
     * 
     * @return float
     */
    public function getRemainingBalance(): float
    {
        $totalAmount = $this->getTotalAmount();
        
        // El saldo es el monto total menos los gastos APROBADOS vinculados al préstamo
        $totalPaid = $this->payments()
            ->where('status', Expense::STATUS_APPROVED)
            ->sum('amount');

        return (float) max(0, $totalAmount - $totalPaid);
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
     * Accessor para el saldo pendiente
     */
    public function getRemainingBalanceAttribute(): float
    {
        return $this->getRemainingBalance();
    }

    /**
     * Accessor para el monto total
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->getTotalAmount();
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
