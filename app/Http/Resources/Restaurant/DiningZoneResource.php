<?php

namespace App\Http\Resources\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiningZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'background_style' => $this->background_style,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'tables_count' => $this->tables()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
