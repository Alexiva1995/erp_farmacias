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
        $rawCost = 0.0;
        $hasIngredients = $this->relationLoaded('ingredients') && $this->ingredients && $this->ingredients->isNotEmpty();

        $ingredientsData = [];
        if ($hasIngredients) {
            foreach ($this->ingredients as $product) {
                $portion = (float) ($product->pivot?->portion ?? 0);
                $unitCost = (float) ($product->unit_cost ?? 0);
                $presentation = (float) ($product->presentation ?? 1);
                $safePresentation = $presentation > 0 ? $presentation : 1.0;

                // Calcular costo dinámico del ingrediente según el costo del insumo en inventario
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
                    'is_removable' => (bool) ($product->pivot?->is_removable ?? false),
                ];
            }
        }

        // Si tiene ingredientes calculados, usar el costo dinámico sumando el 5% de imprevisto
        $costPrice = ($hasIngredients && $rawCost > 0)
            ? round($rawCost * 1.05, 2)
            : (float) $this->cost_price;

        $profitMultiplier = (float) ($this->percentage_profit ?? 0);
        $suggestedPrice = ($hasIngredients && $rawCost > 0)
            ? round($costPrice * $profitMultiplier, 2)
            : (float) $this->suggested_price;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'cost_price' => $costPrice,
            'cpv' => (float) ($this->cpv ?? $costPrice),
            'suggested_price' => $suggestedPrice,
            'designated_price' => (float) $this->designated_price,
            'designated_price_cop' => $this->designated_price_cop !== null ? (float) $this->designated_price_cop : null,
            'percentage_profit' => (float) $this->percentage_profit,
            'category_id' => $this->category_id,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'status' => (int) $this->status,
            'iva' => (bool) $this->iva,
            'tax_rate' => (float) ($this->tax_rate ?? 16.0),
            'preparation_time_minutes' => (int) ($this->preparation_time_minutes ?? 15),
            'kitchen_station_id' => $this->kitchen_station_id ?? null,
            'kitchen_station' => ($this->relationLoaded('kitchenStation') && $this->kitchenStation) ? [
                'id' => $this->kitchenStation->id,
                'name' => $this->kitchenStation->name,
                'code' => $this->kitchenStation->code,
                'color' => $this->kitchenStation->color,
                'icon' => $this->kitchenStation->icon,
            ] : null,
            'ingredients' => $ingredientsData,
            'modifier_groups' => $this->whenLoaded('modifierGroups', function () {
                return $this->modifierGroups->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'base_product_id' => $group->base_product_id,
                        'min_selections' => (int) $group->min_selections,
                        'max_selections' => (int) $group->max_selections,
                        'options' => $group->options ? $group->options->map(function ($opt) {
                            return [
                                'id' => $opt->id,
                                'name' => $opt->name,
                                'modifier_type' => $opt->modifier_type instanceof \App\Enums\ModifierTypeEnum ? $opt->modifier_type->value : $opt->modifier_type,
                                'extra_price_usd' => (float) $opt->extra_price_usd,
                                'product_id' => $opt->product_id,
                                'portion' => (float) $opt->portion,
                                'is_active' => (bool) $opt->is_active,
                            ];
                        }) : [],
                    ];
                });
            }),
            'expected_yield_quantity' => (float) ($this->expected_yield_quantity ?? 1.0),
            'unit_cost' => (float) ($this->expected_yield_quantity ?? 1.0) > 0
                ? round($costPrice / (float) ($this->expected_yield_quantity ?? 1.0), 4)
                : $costPrice,
            'output_product_id' => $this->output_product_id,
            'output_product' => ($this->relationLoaded('outputProduct') && $this->outputProduct) ? [
                'id' => $this->outputProduct->id,
                'name' => $this->outputProduct->name,
                'stock' => (float) $this->outputProduct->stock,
                'unit_cost' => (float) ($this->expected_yield_quantity ?? 1.0) > 0
                    ? round($costPrice / (float) ($this->expected_yield_quantity ?? 1.0), 4)
                    : (float) $this->outputProduct->unit_cost,
                'unit_of_measure' => $this->outputProduct->unit_of_measure,
            ] : null,
            'expected_yield_quantity' => (float) ($this->expected_yield_quantity ?: 1.0),
            'shelf_life_days' => (int) ($this->shelf_life_days ?: 7),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
