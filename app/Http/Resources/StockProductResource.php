<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockProductResource extends JsonResource
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
            'active_ingredient' => $this->active_ingredient ?? $this->active_component,
            'active_component' => $this->active_component ?? $this->active_ingredient,
            'unit_cost' => (float) ($this->unit_cost ?? 0),
            'sale_price' => (float) ($this->sale_price ?? 0),
            'lote_quantity' => (float) ($this->lote_quantity ?? $this->stock ?? 0),
            'stock' => (float) ($this->stock ?? $this->lote_quantity ?? 0),
            'total_sold_completed' => (float) ($this->total_sold_completed ?? 0),
            'preferencia_product' => (float) ($this->preferencia_product ?? 100),
            'promedio_calculado' => (float) ($this->promedio_calculado ?? 0),
            'totalQuantityInAutoOrder' => (float) ($this->totalQuantityInAutoOrder ?? 0),
            'diferencia_product' => (float) ($this->diferencia_product ?? 0),
            'is_colombian_origin' => (int) ($this->is_colombian_origin ?? 0),
            'is_novaventa' => (int) ($this->is_novaventa ?? 0),
            'presentation' => $this->presentation,
            'unit_of_measure' => $this->unit_of_measure,
            'photo_url' => $this->photo_url,
            'laboratory_id' => $this->laboratory_id,
            'laboratory' => $this->whenLoaded('laboratory', function () {
                return [
                    'id' => $this->laboratory->id ?? null,
                    'name' => $this->laboratory->name ?? null,
                ];
            }, [
                'id' => $this->laboratory_id ?? null,
                'name' => $this->laboratory_name ?? optional($this->laboratory)->name,
            ]),
            'group_id' => $this->group_id,
            'group_name' => $this->group_name ?? optional($this->group)->name,
            'productos' => isset($this->productos) && $this->productos instanceof \Illuminate\Support\Collection
                ? StockProductResource::collection($this->productos)
                : (is_array($this->productos) ? StockProductResource::collection(collect($this->productos)) : []),
        ];
    }
}
