<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierGroupResource extends JsonResource
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
            'description' => $this->description,
            'min_selections' => (int) $this->min_selections,
            'max_selections' => (int) $this->max_selections,
            'is_active' => (bool) $this->is_active,
            'options' => $this->whenLoaded('options', function () {
                return $this->options->map(function ($opt) {
                    return [
                        'id' => $opt->id,
                        'name' => $opt->name,
                        'modifier_type' => $opt->modifier_type instanceof \App\Enums\ModifierTypeEnum ? $opt->modifier_type->value : $opt->modifier_type,
                        'extra_price_usd' => (float) $opt->extra_price_usd,
                        'product_id' => $opt->product_id,
                        'portion' => (float) $opt->portion,
                        'sort_order' => (int) $opt->sort_order,
                        'is_active' => (bool) $opt->is_active,
                        'product' => $opt->product ? [
                            'id' => $opt->product->id,
                            'name' => $opt->product->name,
                            'unit_cost' => (float) $opt->product->unit_cost,
                            'presentation' => (float) $opt->product->presentation,
                            'unit_of_measure' => $opt->product->unit_of_measure,
                        ] : null,
                    ];
                });
            }),
            'dishes_count' => $this->whenCounted('dishes'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
