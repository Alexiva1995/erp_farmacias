<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PurchaseBookContract;
use App\Contracts\SalesBookContract;
use App\Repositories\PurchaseBookRepository;
use App\Repositories\SalesBookRepository;
use Illuminate\Support\ServiceProvider;

class PurchaseBookServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(PurchaseBookContract::class, PurchaseBookRepository::class);
        $this->app->bind(SalesBookContract::class, SalesBookRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
