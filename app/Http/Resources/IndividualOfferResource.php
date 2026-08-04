<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndividualOfferResource extends JsonResource
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
            'discount_percent' => $this->discount_percent,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'sales_count' => (int) ($this->sales_count ?? 0),
            'product' => $this->whenLoaded('product'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
