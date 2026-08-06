<?php

namespace App\Providers;

use App\Models\ExpiredLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Models\InventoryMovement;
use App\Observers\InventoryMovementObserver;
use App\Observers\ProductLotObserver;
use App\Observers\ProductObserver;
use App\Observers\OrderObserver;
use App\Observers\InvoiceObserver;
use App\Observers\ExpiredLogObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * The model observers for your application.
     *
     * @var array
     */
    protected $observers = [
        ProductLot::class => [ProductLotObserver::class],
        Product::class => [ProductObserver::class],
        Order::class => [OrderObserver::class],
        Invoice::class => [InvoiceObserver::class],
        ExpiredLog::class => [ExpiredLogObserver::class],
        InventoryMovement::class => [InventoryMovementObserver::class],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
