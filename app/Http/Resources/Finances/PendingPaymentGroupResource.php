<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PendingPaymentGroupResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        // El recurso recibe un array asociativo del grupo precalculado
        return [
            'supplier_id' => $this['supplier_id'],
            'supplier_name' => $this['supplier_name'],
            'payment_date' => $this['payment_date'] instanceof \Carbon\Carbon 
                ? $this['payment_date']->toDateString() 
                : $this['payment_date'],
            'currency' => $this['currency'],
            'total_amount' => (float)$this['total_amount'],
            'total_usd' => (float)$this['total_usd'],
            'remainingAmountUSD' => (float)$this['remainingAmountUSD'],
            'total_in_supplier_currency' => (float)$this['total_in_supplier_currency'],
            'supplier_preferred_currency' => $this['supplier_preferred_currency'],
            'invoice_count' => (int)$this['invoice_count'],
            'invoices' => collect($this['invoices'])->map(function ($invoice) {
                return [
                    'id' => $invoice['id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'control_number' => $invoice['control_number'] ?? 'N/A',
                    'supplier_rif' => $invoice['supplier_rif'] ?? 'N/A',
                    'total_amount' => (float)$invoice['total_amount'],
                    'total_usd' => (float)$invoice['total_usd'],
                    'invoiceRemainingUSD' => (float)$invoice['invoiceRemainingUSD'],
                    'remaining_amount' => (float)$invoice['remaining_amount'],
                    'remaining_amount_usd' => (float)$invoice['remaining_amount_usd'],
                    'original_amount' => (float)$invoice['original_amount'],
                    'original_amount_usd' => (float)$invoice['original_amount_usd'],
                    'currency' => $invoice['currency'],
                    'is_indexed' => (bool)$invoice['is_indexed'],
                    'indexed_data' => $invoice['indexed_data'],
                    'exchange_rate' => $invoice['exchange_rate'] !== null ? (float)$invoice['exchange_rate'] : null,
                    'exp_date' => $invoice['exp_date'] instanceof \Carbon\Carbon 
                        ? $invoice['exp_date']->toDateString() 
                        : $invoice['exp_date'],
                    'supplier_total_bs' => (float)$invoice['supplier_total_bs'],
                    'supplier_total_usd' => (float)$invoice['supplier_total_usd'],
                ];
            })->all(),
        ];
    }
}
