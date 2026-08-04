<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPackResource extends JsonResource
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
            'name' => $this->name,
            'pack_config' => $this->pack_config,
            'products_info' => $this->products_info,
            'products_count' => $this->products_count,
            'total_price' => (float) $this->total_price,
            'max_quantity' => $this->max_quantity,
            'max_sale_date' => $this->max_sale_date,
            'is_active' => (bool) $this->is_active,
            'is_available' => (bool) $this->is_available,
            'sales_count' => (int) ($this->sales_count ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
