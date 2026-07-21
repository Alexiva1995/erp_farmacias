<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'type_company' => $this->type_company ?? 'Empresa',
            'current_discount' => (float) ($this->current_discount ?? 0),
            'clients_count' => $this->whenCounted('clients', $this->clients_count ?? ($this->relationLoaded('clients') ? $this->clients->count() : 0)),
            'clients' => ClientResource::collection($this->whenLoaded('clients')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
