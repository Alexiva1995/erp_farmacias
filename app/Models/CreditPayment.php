<?php

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
    ];

    protected $casts = [
        'method_Payment' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
