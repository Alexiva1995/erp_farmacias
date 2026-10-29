<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Credit extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'order_id',
        'credit_amount',
        'pending_amount',
        'credit_date',
        'status',
        'credit_type',
        'cashea_order_reference',
        'initial_payment_usd',
        'installments_count',
        'next_due_date',
        'invoice_number',
        'control_number',
        'exchange_rate_issue',
        'is_indexed',
        'early_payment_discount_pct',
        'early_payment_expires_at',
        'withheld_iva_amount_usd',
        'withheld_islr_amount_usd',
        'sales_rep_id',
        'collector_user_id',
    ];

    protected $casts = [
        'credit_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
        'initial_payment_usd' => 'decimal:2',
        'installments_count' => 'integer',
        'next_due_date' => 'date',
        'credit_date' => 'datetime',
        'exchange_rate_issue' => 'decimal:4',
        'is_indexed' => 'boolean',
        'early_payment_discount_pct' => 'decimal:2',
        'early_payment_expires_at' => 'date',
        'withheld_iva_amount_usd' => 'decimal:2',
        'withheld_islr_amount_usd' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(CreditPayment::class, 'client_id', 'client_id');
    }

    public function installments()
    {
        return $this->hasMany(CreditInstallment::class, 'credit_id');
    }

    public function salesRep()
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function collectorUser()
    {
        return $this->belongsTo(User::class, 'collector_user_id');
    }

    public function allocations()
    {
        return $this->hasMany(CreditPaymentAllocation::class, 'credit_id');
    }
}
