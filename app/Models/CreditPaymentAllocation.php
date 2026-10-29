<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPaymentAllocation extends Model
{
    use HasFactory;

    protected $table = 'credit_payment_allocations';

    protected $fillable = [
        'credit_payment_id',
        'credit_id',
        'amount_allocated_usd',
        'amount_allocated_original',
        'currency',
        'early_payment_discount_applied',
    ];

    protected $casts = [
        'amount_allocated_usd' => 'decimal:2',
        'amount_allocated_original' => 'decimal:2',
        'early_payment_discount_applied' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CreditPayment::class, 'credit_payment_id');
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class, 'credit_id');
    }
}
