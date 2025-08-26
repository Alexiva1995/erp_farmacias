<?php

namespace App\Observers;

use App\Models\Invoice;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        // Cuando se crea una factura, generar movimientos de inventario
        if ($invoice->status === 'received' || $invoice->status === 'processed') {
            ProductObserver::handleInvoiceMovement($invoice);
        }
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        if (
            $invoice->isDirty('status') &&
            ($invoice->status === 'received' || $invoice->status === 'processed') &&
            ($invoice->getOriginal('status') !== 'received' && $invoice->getOriginal('status') !== 'processed')
        ) {

            ProductObserver::handleInvoiceMovement($invoice);
        }
    }
}
