<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\Resources\ResourceService;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
    ];

    protected $appends = ['total_bs_in_usd', 'total_cop_in_usd'];

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
}
