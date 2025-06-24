<?php

namespace App\Providers;

use App\Contracts\Client;
use App\Http\Controllers\Api\ClientController;
use App\Services\ClientServices;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
