<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorOfferResource extends JsonResource
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
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->doctor_name ?? $this->doctor?->name ?? 'N/A',
            'doctor' => $this->whenLoaded('doctor'),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'discount' => (float) $this->discount,
            'is_active' => (bool) $this->is_active,
            'scales' => $this->whenLoaded('scales'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
