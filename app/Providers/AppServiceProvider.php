<?php

namespace App\Providers;

use App\Contracts\Client;
use App\Contracts\Company;
use App\Contracts\Doctor;
use App\Contracts\Laboratory;
use App\Contracts\Lottery;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\LaboratoryController;
use App\Http\Controllers\Api\LotteryController;
use App\Services\ClientServices;
use App\Services\CompanyServices;
use App\Services\DoctorServices;
use App\Services\LaboratoryServices;
use App\Services\LotteryServices;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        $this->app->when(LaboratoryController::class)
            ->needs(Laboratory::class)
            ->give(LaboratoryServices::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
