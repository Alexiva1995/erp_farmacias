<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentHistoryResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'payment_date' => $this->payment_date ? $this->payment_date->toDateString() : null,
            'payment_method' => $this->payment_method,
            'currency' => $this->currency ?? $this->payment_method,
            'amount' => (float)$this->amount,
            'amount_usd' => (float)($this->amount_usd ?? 0),
            'reference' => $this->reference,
            'photo_url' => $this->photo_url,
            'notes' => $this->notes,
            'payment_type' => $this->payment_type,
            'invoice_total_usd' => (float)($this->invoice_total_usd ?? 0),
            'total_paid_usd' => isset($this->total_paid_usd) ? (float)$this->total_paid_usd : null,
            'remaining_amount_usd' => isset($this->remaining_amount_usd) ? (float)$this->remaining_amount_usd : null,
            'payment_percentage' => isset($this->payment_percentage) ? (float)$this->payment_percentage : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'invoices' => $this->invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => (float)$invoice->total_amount,
                    'total_usd' => (float)$invoice->total_usd,
                    'currency' => $invoice->currency,
                    'supplier' => $invoice->supplier ? [
                        'id' => $invoice->supplier->id,
                        'name' => $invoice->supplier->name,
                    ] : null,
                ];
            })->all(),
        ];
    }
}
