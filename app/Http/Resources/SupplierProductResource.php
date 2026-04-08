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
            'laboratory_name' => $this->laboratory,
            'unit_cost' => $this->unit_cost,
            'unit_cost_usd' => $this->unit_cost_usd,
            'final_cost_usd' => $this->unit_cost_usd_with_discount ?? $this->unit_cost_usd,
            'supplier_name' => $this->supplier?->name ?? 'N/A',
        ];
    }
}
