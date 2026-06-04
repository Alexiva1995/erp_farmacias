<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DishResource extends JsonResource
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
            'cost_price' => (float) $this->cost_price,
            'cpv' => (float) $this->cpv,
            'suggested_price' => (float) $this->suggested_price,
            'designated_price' => (float) $this->designated_price,
            'percentage_profit' => (float) $this->percentage_profit,
            'category_id' => $this->category_id,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'status' => (int) $this->status,
            'ingredients' => $this->ingredients->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'unit_cost' => (float) $product->unit_cost,
                    'presentation' => (float) $product->presentation,
                    'unit_of_measure' => $product->unit_of_measure,
                    'portion' => (float) $product->pivot->portion,
                    'designated_cost' => (float) $product->pivot->designated_cost,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
