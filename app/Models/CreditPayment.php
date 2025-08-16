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
        'payment_methods',
    ];
}
