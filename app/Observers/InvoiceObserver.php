<?php

namespace App\Observers;

use App\Models\Invoice;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     * NO crear movimientos aquí. Los movimientos de inventario para facturas se crean
     * únicamente al aprobar la factura cargada (InvoiceActionService::approveInvoice).
     */
    public function created(Invoice $invoice): void
    {
        // Los movimientos se crean solo cuando se aprueba la factura (loaded → to_order),
        // no al cargar (invoiceLoaded) ni al ordenar/archivar (invoice ordered).
    }

    /**
     * Handle the Invoice "updated" event.
     * NO crear movimientos por cambio de status. Los movimientos se crean únicamente
     * en approveInvoice (loaded → to_order). Ni "cargada" ni "ordenada" crean movimientos.
     */
    public function updated(Invoice $invoice): void
    {
        // Los movimientos de inventario (purchase) para facturas se crean solo en
        // InvoiceActionService::approveInvoice cuando status pasa de 'loaded' a 'to_order'.
        // No se crean al cargar la factura ni al archivarla (ordered).
    }
}
