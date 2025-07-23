<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountRuleResource extends JsonResource
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
            'laboratory_name' => $this->supplierLaboratory->laboratory->name ?? '—',
            'scale_type' => $this->scale_type === 'units' ? 'Unidades' : 'Dólares',
            'min' => $this->scale_type === 'units' ? $this->min_quantity : $this->min_amount,
            'max' => $this->scale_type === 'units' ? $this->max_quantity : $this->max_amount,
            'discount_percentage' => (float) $this->discount_percentage,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
