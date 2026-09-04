<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncompleteProductResource extends JsonResource
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
            'barcode' => $this->barcode,
            'active_ingredient' => $this->active_ingredient,
            'presentation' => $this->presentation,
            'unit_of_measure' => $this->unit_of_measure,
            'psychotropic' => (bool) $this->psychotropic,
            'iva' => (bool) $this->iva,
            'is_colombian_origin' => (bool) $this->is_colombian_origin,
            'laboratory_id' => $this->laboratory_id,
            'origin_id' => $this->origin_id,
            'category_id' => $this->category_id,
            'stock_calculado' => $this->stock_calculado ?? 0,
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
            'category' => $this->whenLoaded('category', function () {
                return $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ] : null;
            }),
        ];
    }
}
