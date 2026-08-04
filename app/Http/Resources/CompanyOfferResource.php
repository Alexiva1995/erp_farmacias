<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyOfferResource extends JsonResource
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
            'company_id' => $this->company_id,
            'company_name' => $this->company?->name ?? 'N/A',
            'company' => $this->whenLoaded('company'),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => (bool) $this->is_active,
            'sales_count' => (int) ($this->sales_count ?? 0),
            'sales_amount' => (float) ($this->sales_amount ?? 0),
            'scales' => $this->scales ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
