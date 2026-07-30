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
            'active_component' => $this->active_component,
            'stock' => $this->stock,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'laboratory_id' => $this->laboratory_id,
            'laboratory_name' => $this->laboratory_name ?? optional($this->laboratory)->name,
            'group_id' => $this->group_id,
            'group_name' => $this->group_name ?? optional($this->group)->name,
            'expiration_date' => $this->expiration_date,
            'days_to_expiration' => $this->days_to_expiration,
            'average_sales' => $this->average_sales ?? 0,
            'total_sales' => $this->total_sales ?? 0,
            'stock_status' => $this->stock_status ?? null,
            'productos' => StockProductResource::collection($this->whenLoaded('productos', $this->productos ?? [])),
        ];
    }
}
