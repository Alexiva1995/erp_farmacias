<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCashClosure extends Model
{
    protected $table = 'daily_closures';

    protected $fillable = [
        'total_usd',
        'total_cop',
        'total_bs',
        'bs_card',
        'bs_mobile',
        'usd_delivered',
        'cop_delivered',
        'bs_delivered',
    ];

    public function cashClosings()
    {
        return $this->hasMany(CashClosing::class);
    }
}
