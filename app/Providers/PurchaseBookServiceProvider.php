<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\InventoryBookContract;
use App\Contracts\PurchaseBookContract;
use App\Contracts\SalesBookContract;
use App\Contracts\VatRetentionBookContract;
use App\Repositories\InventoryBookRepository;
use App\Repositories\PurchaseBookRepository;
use App\Repositories\SalesBookRepository;
use App\Repositories\VatRetentionBookRepository;
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
        $this->app->bind(InventoryBookContract::class, InventoryBookRepository::class);
        $this->app->bind(VatRetentionBookContract::class, VatRetentionBookRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
