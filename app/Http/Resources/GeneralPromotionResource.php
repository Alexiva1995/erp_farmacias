<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralPromotionResource extends JsonResource
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
            'type' => $this->type,
            'fixed_price' => $this->fixed_price,
            'discount_percentage' => $this->getEffectiveDiscountPercentage(),
            'is_active' => (bool) $this->is_active,
            'categories' => $this->categories,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'days_of_week' => $this->days_of_week ?? [],
            'start_time' => $this->start_time ? substr((string) $this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
            'min_order_amount' => $this->min_order_amount,
            'coupon_code' => $this->coupon_code,
            'sales_channel' => $this->sales_channel ?? 'all',
            'stackable' => (bool) ($this->stackable ?? false),
            'priority' => (int) ($this->priority ?? 10),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
