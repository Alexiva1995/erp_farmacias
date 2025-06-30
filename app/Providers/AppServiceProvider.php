<?php

namespace App\Providers;

use App\Contracts\Profitability;
use App\Http\Controllers\Api\ProfitabilityController;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
