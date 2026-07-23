<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FurnitureResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentValue = $this->getCurrentValue();
        $totalDepreciation = $this->getTotalDepreciationAmount();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cost' => (float) $this->cost,
            'acquisition_year' => (int) $this->acquisition_year,
            'annual_depreciation_rate' => (float) $this->annual_depreciation_rate,
            'current_value' => (float) $currentValue,
            'total_depreciation_amount' => (float) $totalDepreciation,
            'depreciation_percentage' => $this->cost > 0 ? round(($totalDepreciation / $this->cost) * 100, 2) : 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
