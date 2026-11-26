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
        $logoUrl = \App\Models\Supplier::resolveLogoUrl($this->logo_url ?? $this->master_logo_url ?? null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_url' => $logoUrl,
            'photo_url' => $logoUrl,
            'public_token' => $this->public_token,
            'last_connection' => $this->last_connection,
            'type' => $this->type,
            'is_active' => (bool) ($this->is_active ?? true),
        ];
    }
}
