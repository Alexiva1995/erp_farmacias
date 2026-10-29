<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditPayment extends Model
{
    protected $fillable = [
        'client_id',
        'seller_id',
        'cash_closing_id',
        'money_returns',
        'payment_date',
        'method_Payment',
        'status',
        'credit_id',
        'reference_number',
        'bank_name',
        'receipt_photo_url',
        'exchange_rate_applied',
        'amount_usd_equivalent',
        'source_channel',
        'external_tenant_id',
        'external_invoice_payment_id',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'withheld_iva_amount',
        'withheld_islr_amount',
        'retention_voucher_number',
        'retention_voucher_photo_url',
    ];

    protected $casts = [
        'method_Payment' => 'array',
        'exchange_rate_applied' => 'decimal:4',
        'amount_usd_equivalent' => 'decimal:2',
        'withheld_iva_amount' => 'decimal:2',
        'withheld_islr_amount' => 'decimal:2',
        'verified_at' => 'datetime',
        'payment_date' => 'datetime',
    ];

    public function credit()
    {
        return $this->belongsTo(Credit::class, 'credit_id');
    }

    public function allocations()
    {
        return $this->hasMany(CreditPaymentAllocation::class, 'credit_payment_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
