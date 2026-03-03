<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RetentionResource extends JsonResource
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
            'number' => $this->number,
            'date' => $this->date?->format('Y-m-d'),
            'total_taxable_base' => (float)$this->total_taxable_base,
            'total_tax_amount' => (float)$this->total_tax_amount,
            'total_withheld_amount' => (float)$this->total_withheld_amount,
            'retention_percentage' => (float)$this->retention_percentage,
            'supplier' => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
                'social_reason' => $this->supplier?->social_reason,
                'rif' => $this->supplier?->rif,
            ],
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
