<?php

namespace App\Http\Resources\Islr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxUnitResource extends JsonResource
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
            'value' => (float) $this->value,
            'effective_date' => $this->effective_date ? $this->effective_date->format('Y-m-d') : null,
            'notes' => $this->notes,
            'currency' => 'VES',
        ];
    }
}
