<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Services\Resources\ResourceService;

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
        'total_sales',
        'total_usd',
        'total_cop',
        'total_bs',
        'bs_card',
        'bs_cash',
        'bs_transfer',
        'bs_mobile',
        'cop_cash',
        'cop_transfer',
        'cop_conversion',
        'cop_spare',
        'usd_transfer',
        'usd_cash',
        'usd_paypal',
        'usd_binance',
        'usd_conversion',
        'usd_credit',
        'usd_balance',
        'usd_delivered',
        'cop_delivered',
        'bs_delivered',
        'bs_card_payment_credit',
        'bs_cash_payment_credit',
        'bs_transfer_payment_credit',
        'bs_mobile_payment_credit',
        'cop_cash_payment_credit',
        'cop_transfer_payment_credit',
        'cop_conversion_payment_credit',
        'usd_transfer_payment_credit',
        'usd_cash_payment_credit',
        'usd_paypal_payment_credit',
        'usd_binance_payment_credit',
    ];

    protected $appends = ['total_bs_in_usd', 'total_cop_in_usd'];

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

    public function dailyClosure()
    {
        return $this->belongsTo(DailyCashClosure::class);
    }


    protected function getServiceExchangeRate(string $currencyCode): float
    {
        $resourceService = app(ResourceService::class);
        return $resourceService->getExchangeRate($currencyCode);
    }
    /**
     * Accesor para el TOTAL en Bolívares (BS). Coventido EN USD
     */
    protected function totalBsInUsd(): Attribute
    {
        return Attribute::make(
            get: fn() => round($this->total_bs / $this->getServiceExchangeRate('BS'), 2),
        );
    }

    /**
     * Accesor para el precio en Pesos Colombianos (COP). Coventido EN USD
     */
    protected function totalCopInUsd(): Attribute
    {
        return Attribute::make(
            get: fn() => round($this->total_cop / $this->getServiceExchangeRate('COP'), 2),
        );
    }
}
