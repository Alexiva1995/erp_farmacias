<?php

namespace App\Http\Resources\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubRecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cost_price' => (float) $this->cost_price,
            'expected_yield_quantity' => (float) ($this->expected_yield_quantity ?: 1.0),
            'shelf_life_days' => (int) ($this->shelf_life_days ?: 7),
            'output_product_id' => $this->output_product_id,
            'output_product' => $this->outputProduct ? [
                'id' => $this->outputProduct->id,
                'name' => $this->outputProduct->name,
                'stock' => (float) $this->outputProduct->stock,
                'unit_cost' => (float) $this->outputProduct->unit_cost,
                'unit_of_measure' => $this->outputProduct->unit_of_measure,
            ] : null,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'status' => (int) $this->status,
            'ingredients' => $this->ingredients ? $this->ingredients->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'unit_cost' => (float) $product->unit_cost,
                    'presentation' => (float) $product->presentation,
                    'unit_of_measure' => $product->unit_of_measure,
                    'portion' => (float) ($product->pivot?->portion ?? 0),
                    'designated_cost' => (float) ($product->pivot?->designated_cost ?? 0),
                ];
            }) : [],
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
        ];
    }
}
