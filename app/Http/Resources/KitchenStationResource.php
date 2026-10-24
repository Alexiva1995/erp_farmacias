<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenStationResource extends JsonResource
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
            'code' => $this->code,
            'color' => $this->color,
            'icon' => $this->icon,
            'printer_ip' => $this->printer_ip,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'dishes_count' => $this->whenCounted('dishes'),
        ];
    }
}
