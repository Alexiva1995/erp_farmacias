<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocialBenefitEmployeeResource extends JsonResource
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
            'last_name' => $this->last_name,
            'identification' => $this->identification,
            'email' => $this->email ?? $this->user?->email,
            'position' => [
                'name' => $this->role_name ?? $this->user?->role?->name ?? 'Empleado',
            ],
            'created_at' => $this->created_at ? (is_string($this->created_at) ? $this->created_at : $this->created_at->format('Y-m-d')) : null,
            'settlement_date' => $this->settlement_date,
            'signed_document_path' => $this->signed_document_path,
        ];
    }
}
