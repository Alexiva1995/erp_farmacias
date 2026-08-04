<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLaboratoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->id,
            'employee_name' => trim($this->name . ' ' . $this->last_name),
            'identification' => $this->identification,
            'is_active' => (bool) $this->is_active,
            'laboratories' => $this->relationLoaded('laboratories')
                ? $this->laboratories->map(fn($lab) => [
                    'id' => $lab->id,
                    'name' => $lab->name,
                ])
                : [],
            'laboratories_count' => $this->laboratories_count ?? 0,
            'photo_url' => $this->photo_url,
        ];
    }
}
