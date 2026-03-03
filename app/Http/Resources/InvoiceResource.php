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
            'invoice_number' => $this->invoice_number,
            'control_number' => $this->control_number,
            'created_invoice_date' => $this->created_invoice_date,
            'taxable_base' => (float)$this->taxable_base,
            'tax_amount' => (float)$this->tax_amount,
            'total_amount' => (float)$this->total_amount,
            'retention_generated' => (bool)$this->retention_generated,
            'supplier' => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
                'social_reason' => $this->supplier?->social_reason,
                'rif' => $this->supplier?->rif,
            ],
        ];
    }
}
