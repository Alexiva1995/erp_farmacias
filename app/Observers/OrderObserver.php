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
        if ($order->status === Order::COMPLETED || $order->status === 'paid') {
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
            ($order->status === Order::COMPLETED || $order->status === 'paid') &&
            ($order->getOriginal('status') !== Order::COMPLETED && $order->getOriginal('status') !== 'paid')
        ) {

            ProductObserver::handleOrderMovement($order);
        }
    }
}
