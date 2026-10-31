<?php

declare(strict_types=1);

namespace App\Http\Resources\Wholesale;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRouteResource extends JsonResource
{
    /**
     * Transforma el recurso de ruta logística a un arreglo para la API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'zone_state' => $this->zone_state ? (string) $this->zone_state : null,
            'driver_name' => $this->driver_name ? (string) $this->driver_name : null,
            'vehicle_plate' => $this->vehicle_plate ? (string) $this->vehicle_plate : null,
            'dispatch_days' => $this->dispatch_days,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
