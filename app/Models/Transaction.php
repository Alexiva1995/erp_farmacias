<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = ["user_id", "category_id", "exchange_rate_id", "description", "currency", "type", "amount", "movement_type", "transaction_date"];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->hasOne(ExpenseCategory::class);
    }

    public function exchange()
    {
        return $this->hasOne(ExchangeRate::class);
    }
}
