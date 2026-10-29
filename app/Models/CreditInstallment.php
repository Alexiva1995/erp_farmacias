<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditInstallment extends Model
{
    use HasFactory;

    protected $table = 'credit_installments';

    protected $fillable = [
        'credit_id',
        'installment_number',
        'amount_usd',
        'paid_amount_usd',
        'due_date',
        'paid_at',
        'payment_reference',
        'status',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'amount_usd' => 'decimal:2',
        'paid_amount_usd' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class, 'credit_id');
    }
}
