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
    ];

    protected $casts = [
        'credit_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
        'initial_payment_usd' => 'decimal:2',
        'installments_count' => 'integer',
        'next_due_date' => 'date',
        'credit_date' => 'datetime',
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
}
