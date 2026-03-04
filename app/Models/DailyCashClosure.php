<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\Resources\ResourceService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Collection;

class DailyCashClosure extends Model
{
    protected $table = 'daily_closures';

    protected $fillable = [
        'total_sales',
        'total_usd',
        'total_cop',
        'total_bs',
        'bs_card',
        'bs_mobile',
        'usd_delivered',
        'cop_delivered',
        'bs_delivered',
        'total_credits',
        'total_payment_credit',
        'total_delivery',
    ];

    protected $appends = ['total_bs_in_usd', 'total_cop_in_usd', 'exchange_rate', 'cop_exchange_rate'];

    public function exchangeRate(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getServiceExchangeRate('BS'),
        );
    }

    public function copExchangeRate(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getServiceExchangeRate('COP'),
        );
    }

    public function cashClosings()
{
    return $this->hasMany(CashClosing::class, 'daily_closure_id'); 
}

    protected function getServiceExchangeRate(string $currencyCode): float
    {
        $resourceService = app(ResourceService::class);
        return $resourceService->getExchangeRate($currencyCode);
    }

     protected function totalBsInUsd(): Attribute
    {
        return Attribute::make(
            get: fn() => round($this->total_bs / $this->getServiceExchangeRate('BS'), 2),
        );
    }

        protected function totalCopInUsd(): Attribute
    {
        return Attribute::make(
            get: fn() => round($this->total_cop / $this->getServiceExchangeRate('COP'), 2),
        );
    }

    public function getTotalCopPaymentInUsd(Collection $cashClosings): float
    {
        $totalCop = $cashClosings->sum('cop_transfer_payment_credit') + $cashClosings->sum('cop_conversion_payment_credit');
        $exchangeRate = $this->getServiceExchangeRate('COP');
        if ($exchangeRate > 0) {
            return round($totalCop / $exchangeRate, 2);
        }
        return 0.00;
    }

        public function getTotalBsPaymentInUsd(Collection $cashClosings): float
    {
        $totalBs = $cashClosings->sum('bs_card_payment_credit') + $cashClosings->sum('bs_cash_payment_credit') + $cashClosings->sum('bs_transfer_payment_credit') + $cashClosings->sum('bs_mobile_payment_credit');
        $exchangeRate = $this->getServiceExchangeRate('BS');
        if ($exchangeRate > 0) {
            return round($totalBs / $exchangeRate, 2);
        }
        return 0.00;
    }


    public function getTotalCopDeliveryInUsd(Collection $cashClosings): float
    {
        $totalCop = $cashClosings->sum('cop_delivered') + $cashClosings->sum('cop_transfer');
        $exchangeRate = $this->getServiceExchangeRate('COP');
        if ($exchangeRate > 0) {
            return round($totalCop / $exchangeRate, 2);
        }
        return 0.00;
    }

        public function getTotalBsDeliveryInUsd(Collection $cashClosings): float
    {
        $totalBs = $cashClosings->sum('bs_mobile') + $cashClosings->sum('bs_transfer') + $cashClosings->sum('bs_card') + $cashClosings->sum('bs_cash');
        $exchangeRate = $this->getServiceExchangeRate('BS');
        if ($exchangeRate > 0) {
            return round($totalBs / $exchangeRate, 2);
        }
        return 0.00;
    }
}
