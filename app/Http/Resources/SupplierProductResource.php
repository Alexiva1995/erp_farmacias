<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
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
            'product_id' => $this->product_id,
            'name' => $this->name,
            'laboratory_name' => $this->laboratory_name ?? $this->laboratory ?? 'N/A',
            'unit_cost' => $this->unit_cost_bs ?? $this->unit_cost ?? 0,
            'unit_cost_usd' => $this->unit_cost_usd ?? 0,
            'final_cost_bs' => $this->final_cost_bs ?? $this->unit_cost_with_discount ?? $this->unit_cost ?? 0,
            'final_cost_usd' => $this->final_cost_usd ?? $this->unit_cost_usd_with_discount ?? $this->unit_cost_usd ?? 0,
            'our_cost_usd' => (float) ($this->our_unit_cost_usd ?? $this->product?->unit_cost ?? 0),
            'supplier_name' => $this->supplier_name ?? $this->supplier?->name ?? 'N/A',
            'barcode_match' => $this->barcode_match ?? null,
            'expiration' => $this->expiration ?? null,
            'is_active' => (bool) ($this->is_active ?? true),
        ];
    }
}
