<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CashClosing extends Model
{
    use HasFactory;

    protected $table = 'cash_closing';

    const OPEN = 'open';      
    const CLOSED = 'closed';

    protected $fillable = [
        'seller_id',
        'closing_date',
        'status',
        'total_usd', 'total_cop', 'total_bs',
        'bs_card', 'bs_cash', 'bs_transfer', 'bs_mobile',
        'cop_cash', 'cop_transfer', 'cop_conversion', 'cop_spare',
        'usd_transfer', 'usd_cash', 'usd_paypal', 'usd_binance', 'usd_conversion', 'usd_credit', 'usd_balance',
        'usd_delivered', 'cop_delivered', 'bs_delivered',
        'bs_card_payment_credit', 'bs_cash_payment_credit', 'bs_transfer_payment_credit', 'bs_mobile_payment_credit',
        'cop_cash_payment_credit', 'cop_transfer_payment_credit', 'cop_conversion_payment_credit',
        'usd_transfer_payment_credit', 'usd_cash_payment_credit', 'usd_paypal_payment_credit', 'usd_binance_payment_credit',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function cashFlows()
    {
        return $this->hasMany(CashFlow::class, 'cash_closing_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'cash_closing_id');
    }
}
