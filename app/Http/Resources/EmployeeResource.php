<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'is_active' => (bool)$this->is_active,
            'total_package_usd' => (float)$this->total_package_usd,
            'saldo_deuda' => (float)($this->saldo_deuda ?? 0),
            'email' => $this->user?->email,
            'role' => $this->user?->role,
            'user_id' => $this->user_id,
            'photo' => $this->photo,
            'photo_url' => $this->photo_url,
            'ci_file' => $this->ci_file,
            'rif' => $this->rif,
            'residence_letter' => $this->residence_letter,
            'cv' => $this->cv,
        ];
    }
}
