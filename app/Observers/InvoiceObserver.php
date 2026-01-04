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
        // Crear movimientos cuando el status cambia a 'received' o 'processed'
        if (
            $invoice->isDirty('status') &&
            ($invoice->status === 'received' || $invoice->status === 'processed') &&
            ($invoice->getOriginal('status') !== 'received' && $invoice->getOriginal('status') !== 'processed')
        ) {
            ProductObserver::handleInvoiceMovement($invoice);
        }
        
        // Crear movimientos cuando el status cambia a 'to_order' (cuando se aprueba la factura)
        // Los movimientos se crean aquí para que estén anclados a la factura desde la aprobación
        if (
            $invoice->isDirty('status') &&
            $invoice->status === 'to_order' &&
            $invoice->getOriginal('status') !== 'to_order'
        ) {
            // Solo crear movimientos si no existen ya para esta factura
            if (!$invoice->inventoryMovements()->exists()) {
                ProductObserver::handleInvoiceMovement($invoice);
            }
        }
    }
}
