<?php

namespace App\Providers;

use App\Contracts\Client;
use App\Contracts\Company;
use App\Contracts\Doctor;
use App\Contracts\Lottery;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\LotteryController;
use App\Services\ClientServices;
use App\Services\CompanyServices;
use App\Services\DoctorServices;
use App\Services\LotteryServices;
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
        $this->app->when(ClientController::class)
            ->needs(Client::class)
            ->give(ClientServices::class);

        $this->app->when(CompanyController::class)
            ->needs(Company::class)
            ->give(CompanyServices::class);


        $this->app->when(DoctorController::class)
            ->needs(Doctor::class)
            ->give(DoctorServices::class);

        $this->app->when(LotteryController::class)
            ->needs(Lottery::class)
            ->give(LotteryServices::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
