<?php

namespace App\Providers;

use App\Contracts\Client;
use App\Contracts\Company;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Services\ClientServices;
use App\Services\CompanyServices;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
