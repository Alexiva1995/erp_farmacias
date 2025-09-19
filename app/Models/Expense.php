<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{

    const STATUS_PENDING = "Pending";
    const STATUS_CANCELLED = "Cancelled";
    const STATUS_APPROVED = "Approved";

    const COUNT_EFECTIVO = "Efectivo"; // BS, USD, COP
    const COUNT_TARJETA = "Tarjeta"; // BS,
    const COUNT_PAGO_MOVIL = "Pago Móvil"; // BS,
    const COUNT_TRANSFERENCIA = "Transferencia"; // BS, COP
    const COUNT_BINANCE = "Binance"; // USD
    const COUNT_PAYPAL = "PayPal"; // USD


    protected $fillable = [
        'name',
        'category_id',
        'amount',
        'amount_usd',
        'currency',
        'has_invoice',
        'is_deductible',
        'expense_date',
        'user_id',
        'iva'
        'status',
        'count',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
