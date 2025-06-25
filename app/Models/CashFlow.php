<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CashFlow extends Model
{
    use HasFactory;

    protected $table = 'cash_flow';

    protected $fillable = [
        'cash_closing_id',
        'flow_date',
        'amount_usd',
        'amount_binance',
        'amount_paypal',
        'amount_credit_pending',
        'amount_cop',
        'amount_bancolombia',
        'amount_bs_mobile',
        'amount_bs_transfer',
        'amount_bs_card',
        'amount_bs_cash',
    ];

    public function cashClosing()
    {
        return $this->belongsTo(CashClosing::class, 'cash_closing_id');
    }
}
