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

    const TYPE_OF_EXPENSE_NORMAL = "Normal";
    const TYPE_OF_EXPENSE_RECURRENTE = "Recurrente";

    const RECURRENCE_MENSUAL = "Mensual";
    const RECURRENCE_SEMESTRAL = "Semestral";
    const RECURRENCE_ANUAL = "Anual";


    protected $fillable = [
        'name',
        'category_id',
        'amount',
        'currency',
        'has_invoice',
        'is_deductible',
        'expense_date',
        'user_id',
        'status',
        'count',
        'conversion_rate',
        'exempt_amount',
        'taxable_base',
        'tax_amount',
        'exchange_rate',
        'total_usd',
        'file_name',
        'extension_file',
        'url_file',
        'date_upload',
        'loan_id',
        'approved_by_id',
        'approved_at',
        'cancelled_by_id',
        'cancelled_at',
        'status_note',
    ];

    protected $casts = [
        'is_deductible' => 'boolean',
        'has_invoice' => 'boolean',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function audits()
    {
        return $this->hasMany(ExpenseAudit::class)->orderBy('created_at', 'desc');
    }
}
