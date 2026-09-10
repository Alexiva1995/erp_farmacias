<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpiredLogResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_id' => $this->lot_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'lot_number' => $this->lot_number,
            'expired_quantity' => (int) $this->expired_quantity,
            'cost_per_unit' => $this->cost_per_unit,
            'total_lost_value' => $this->total_lost_value,
            'created_at' => $this->created_at,
            'product' => $this->whenLoaded('product', function() {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'active_ingredient' => $this->product->active_ingredient,
                    'iva' => $this->product->iva,
                    'is_colombian_origin' => $this->product->is_colombian_origin,
                    'psychotropic' => $this->product->psychotropic,
                    'presentation' => $this->product->presentation,
                    'unit_of_measure' => $this->product->unit_of_measure,
                    'laboratory' => $this->product->laboratory ? [
                        'id' => $this->product->laboratory->id,
                        'name' => $this->product->laboratory->name,
                    ] : null,
                ];
            }),
            'donative_log' => $this->whenLoaded('donativeLog'),
        ];
    }
}
