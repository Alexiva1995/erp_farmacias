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
            'iva' => (bool) $this->iva,
            'tax_rate' => (float) ($this->tax_rate ?? 16.0),
            'preparation_time_minutes' => (int) ($this->preparation_time_minutes ?? 15),
            'kitchen_station_id' => $this->kitchen_station_id,
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
            'modifier_groups' => $this->whenLoaded('modifierGroups', function () {
                return $this->modifierGroups->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
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
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
