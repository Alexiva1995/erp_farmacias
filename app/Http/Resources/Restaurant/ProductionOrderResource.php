<?php

namespace App\Http\Resources\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'dish' => [
                'id' => $this->dish?->id,
                'name' => $this->dish?->name,
            ],
            'output_product' => [
                'id' => $this->outputProduct?->id,
                'name' => $this->outputProduct?->name,
            ],
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name ?? $this->user?->username,
            ],
            'batch_multiplier' => (float) $this->batch_multiplier,
            'expected_quantity' => (float) $this->expected_quantity,
            'actual_quantity' => (float) $this->actual_quantity,
            'total_production_cost' => (float) $this->total_production_cost,
            'unit_cost_calculated' => (float) $this->unit_cost_calculated,
            'lot_number' => $this->generatedLot?->lot_number,
            'status' => is_object($this->status) ? $this->status->value : $this->status,
            'status_label' => is_object($this->status) ? $this->status->label() : $this->status,
            'produced_at' => $this->produced_at?->format('d/m/Y H:i'),
        ];
    }
}
