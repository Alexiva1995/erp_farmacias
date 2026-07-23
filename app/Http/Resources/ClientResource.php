<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
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
            'identification' => $this->identification,
            'identification_type' => $this->identification_type,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->name} {$this->last_name}"),
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'birthdate' => $this->birthdate,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn() => $this->company?->name),
            'balance' => (float) ($this->balance ?? 0),
            'is_spe' => (bool) $this->is_spe,
            'status' => $this->status ?? 'active',
            'client_type' => $this->client_type ?? 'Nuevo',
            'cne_verified_at' => $this->cne_verified_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
