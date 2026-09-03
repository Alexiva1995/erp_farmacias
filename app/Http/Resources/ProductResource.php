<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'active_ingredient' => $this->active_ingredient,
            'laboratory_id' => $this->laboratory_id,
            'category_id' => $this->category_id,
            'origin_id' => $this->origin_id,
            'group_id' => $this->group_id,
            'barcode' => $this->barcode,
            'unit_cost' => $this->unit_cost,
            'sale_price' => $this->sale_price,
            'iva' => $this->iva,
            'is_colombian_origin' => (bool) $this->is_colombian_origin,
            'psychotropic' => (bool) $this->psychotropic,
            'is_novaventa' => (bool) $this->is_novaventa,
            'is_scarce' => (bool) $this->is_scarce,
            'is_favorite' => (bool) $this->is_favorite,
            'is_active' => $this->is_active !== 0 && $this->is_active !== false,
            'no_pvp' => (bool) $this->no_pvp,
            'presentation' => $this->presentation,
            'unit_of_measure' => $this->unit_of_measure,
            'stock_calculado' => $this->stock_calculado ?? 0,
            'ultima_fecha_vencimiento' => $this->ultima_fecha_vencimiento,
            'lot_locations' => $this->lot_locations,
            'photo_url' => $this->photo_url,
            'category' => $this->whenLoaded('category', function () {
                return $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ] : null;
            }),
            'laboratory' => $this->whenLoaded('laboratory', function () {
                return $this->laboratory ? [
                    'id' => $this->laboratory->id,
                    'name' => $this->laboratory->name,
                ] : null;
            }),
            'origin' => $this->whenLoaded('origin', function () {
                return $this->origin ? [
                    'id' => $this->origin->id,
                    'name' => $this->origin->name,
                ] : null;
            }),
            'group' => $this->whenLoaded('group', function () {
                return $this->group ? [
                    'id' => $this->group->id,
                    'name' => $this->group->name,
                ] : null;
            }),
            'profitability' => $this->whenLoaded('profitability', function () {
                return $this->profitability ? [
                    'id' => $this->profitability->id,
                    'product_id' => $this->profitability->product_id,
                    'profitability_percentage' => $this->profitability->profitability_percentage,
                    'is_locked' => $this->profitability->is_locked,
                ] : null;
            }),
            'lots' => $this->whenLoaded('lots', function () {
                return $this->lots->map(function ($lot) {
                    return [
                        'id' => $lot->id,
                        'product_id' => $lot->product_id,
                        'lot_number' => $lot->lot_number,
                        'expiration_date' => $lot->expiration_date,
                        'quantity' => $lot->quantity,
                        'location' => $lot->location,
                    ];
                });
            }),
            'variants' => $this->whenLoaded('variants'),
        ];
    }
}
