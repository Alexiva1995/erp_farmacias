<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierConnectionResource extends JsonResource
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
            'logo_url' => $this->logo_url ?? $this->master_logo_url ?? null,
            'photo_url' => $this->logo_url ?? $this->master_logo_url ?? null,
            'public_token' => $this->public_token,
            'last_connection' => $this->last_connection,
            'type' => $this->type,
            'is_active' => (bool) ($this->is_active ?? true),
        ];
    }
}
