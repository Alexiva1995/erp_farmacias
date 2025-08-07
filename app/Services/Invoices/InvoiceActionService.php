<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Exception;
use Illuminate\Support\Facades\DB;

class InvoiceActionService
{
    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {

            $invoiceData = [
                'supplier_id' => $data['supplier_id'],
                'invoice_number' => $data['invoice_number'],
                'control_number' => $data['control_number'],
                'exp_date' => $data['exp_date'],
                'payment_date' => $data['payment_date'] ?? null,
                'received_date' => $data['received_date'],
                'exempt_amount' => $data['exempt_amount'] ?? 0,
                'taxable_base' => $data['taxable_base'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'exchange_rate' => $data['exchange_rate'],
                'total_amount' => $data['total_amount'],
                'total_usd' => $data['total_usd'],
                'currency' => $data['currency'],
                'status' => 'loaded',
                'registered_by' => 1,
                'uploaded_by' => 1
            ];
            return Invoice::create($invoiceData);
        });
    }
    public function deleteInvoice(Invoice $invoice): void
    {
        if ($invoice->payments()->exists()) {
            throw new Exception("No se puede eliminar una factura que ya tiene pagos registrados.");
        }

        DB::transaction(function () use ($invoice) {
            $invoice->delete();
        });
    }
    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {

            $invoiceData = $data['invoice'];
            $invoiceData['status'] = 'Ordered';
            $invoice->update($invoiceData);

            $invoice->details()->delete();

            foreach ($data['details'] as $detail) {
                if ($detail['quantity'] > 0) {
                    $invoice->details()->create([
                        'product_id' => $detail['product']['id'],
                        'quantity' => $detail['quantity'],
                        'unit_cost' => $detail['unit_cost'],
                        'total_cost' => $detail['quantity'] * $detail['unit_cost'],
                    ]);
                }
            }

            return $invoice->fresh(['details.product', 'supplier']);
        });
    }
}
