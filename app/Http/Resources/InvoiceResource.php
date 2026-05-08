<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'invoice_number' => $this->invoice_number,
            'control_number' => $this->control_number,
            'created_invoice_date' => $this->created_invoice_date?->format('Y-m-d'),
            'received_date' => $this->received_date?->format('Y-m-d'),
            'exp_date' => $this->exp_date?->format('Y-m-d'),
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'currency' => $this->currency,
            'exchange_rate' => (float)($this->exchange_rate ?? 0),
            'discount_rule_id' => $this->discount_rule_id,
            'exempt_amount' => (float)($this->exempt_amount ?? 0),
            'taxable_base' => (float)$this->taxable_base,
            'tax_amount' => (float)$this->tax_amount,
            'total_amount' => (float)($this->total_amount ?? 0),
            'total_usd' => (float)($this->total_usd ?? 0),
            'outstanding_debt' => (float)($this->outstanding_debt ?? 0),
            'status' => $this->status,
            'retention_generated' => (bool)($this->retention_generated ?? false),
            'supplier' => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
                'social_reason' => $this->supplier?->social_reason,
                'rif' => $this->supplier?->rif,
            ],
            'uploaded_by_user' => $this->uploadedBy ? [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ] : null,
            'registered_by_user' => $this->registeredBy ? [
                'id' => $this->registeredBy->id,
                'name' => $this->registeredBy->name,
            ] : null,
            'loaded_by_user' => $this->loadedBy ? [
                'id' => $this->loadedBy->id,
                'name' => $this->loadedBy->name,
            ] : null,
            'ordered_by_user' => $this->orderedBy ? [
                'id' => $this->orderedBy->id,
                'name' => $this->orderedBy->name,
            ] : null,
        ];
    }
}
