<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryOfferResource extends JsonResource
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
            'category_id' => $this->category_id,
            'discount_percentage' => $this->discount_percentage,
            'start_date' => $this->start_date?->format('Y-m-d') ?? $this->start_date,
            'end_date' => $this->end_date?->format('Y-m-d') ?? $this->end_date,
            'days_of_week' => $this->days_of_week ?? [],
            'start_time' => $this->start_time ? substr((string) $this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
            'sales_channel' => $this->sales_channel ?? 'all',
            'is_active' => (bool) $this->is_active,
            'category' => $this->whenLoaded('category'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
