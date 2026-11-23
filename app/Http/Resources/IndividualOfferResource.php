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
            'dish_id' => $this->dish_id,
            'discount_percent' => $this->discount_percent,
            'start_date' => $this->start_date?->format('Y-m-d') ?? $this->start_date,
            'end_date' => $this->end_date?->format('Y-m-d') ?? $this->end_date,
            'days_of_week' => $this->days_of_week ?? [],
            'start_time' => $this->start_time ? substr((string) $this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
            'one_per_customer' => (bool) ($this->one_per_customer ?? false),
            'is_active' => (bool) ($this->is_active ?? true),
            'sales_count' => (int) ($this->sales_count ?? 0),
            'product' => $this->whenLoaded('product'),
            'dish' => $this->whenLoaded('dish'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
