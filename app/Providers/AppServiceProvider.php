<?php

namespace App\Providers;

use App\Contracts\Client;
use App\Contracts\Company;
use App\Contracts\Doctor;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Services\ClientServices;
use App\Services\CompanyServices;
use App\Services\DoctorServices;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
