<?php

namespace App\Providers;

use App\Contracts\ExchangeRate;
use App\Contracts\Profitability;
use App\Http\Controllers\api\ExchangeRateController;
use App\Http\Controllers\Api\ProfitabilityController;
use App\Services\ExchangeRateServices;
use App\Services\ProfitabilityServices;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->when(ProfitabilityController::class)
            ->needs(Profitability::class)
            ->give(ProfitabilityServices::class);

        $this->app->when(ExchangeRateController::class)
            ->needs(ExchangeRate::class)
            ->give(ExchangeRateServices::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
