<?php

namespace App\Observers;

use App\Models\Order;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        if ($order->status === 'completed' || $order->status === 'paid') {
            ProductObserver::handleOrderMovement($order);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if (
            $order->isDirty('status') &&
            ($order->status === 'completed' || $order->status === 'paid') &&
            ($order->getOriginal('status') !== 'completed' && $order->getOriginal('status') !== 'paid')
        ) {

            ProductObserver::handleOrderMovement($order);
        }
    }
}
