<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PurchaseBookContract;
use App\Repositories\PurchaseBookRepository;
use Illuminate\Support\ServiceProvider;

class PurchaseBookServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(PurchaseBookContract::class, PurchaseBookRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
