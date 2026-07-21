<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    /**
     * Transformar el recurso en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'identification' => $this->identification,
            'address' => $this->address,
            'specialty_id' => $this->specialty_id,
            'specialty_name' => $this->whenLoaded('specialty', fn() => $this->specialty?->name),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
