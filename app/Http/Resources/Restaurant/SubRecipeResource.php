<?php

namespace App\Http\Resources\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubRecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rawCost = 0.0;
        $hasIngredients = $this->relationLoaded('ingredients') && $this->ingredients && $this->ingredients->isNotEmpty();

        $ingredientsData = [];
        if ($hasIngredients) {
            foreach ($this->ingredients as $product) {
                $portion = (float) ($product->pivot?->portion ?? 0);
                $unitCost = (float) ($product->unit_cost ?? 0);
                $presentation = (float) ($product->presentation ?? 1);
                $safePresentation = $presentation > 0 ? $presentation : 1.0;

                $freshDesignatedCost = $unitCost > 0
                    ? round(($portion * $unitCost) / $safePresentation, 4)
                    : (float) ($product->pivot?->designated_cost ?? 0);

                $rawCost += $freshDesignatedCost;

                $ingredientsData[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'unit_cost' => $unitCost,
                    'presentation' => $presentation,
                    'unit_of_measure' => $product->unit_of_measure,
                    'portion' => $portion,
                    'designated_cost' => $freshDesignatedCost,
                ];
            }
        }

        $costPrice = ($hasIngredients && $rawCost > 0)
            ? round($rawCost * 1.05, 2)
            : (float) $this->cost_price;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'cost_price' => $costPrice,
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
            'ingredients' => $ingredientsData,
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
        ];
    }
}
