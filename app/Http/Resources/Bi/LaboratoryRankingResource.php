<?php

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoryRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'aggregation_id' => (int) ($this->aggregation_id ?? 0),
            'name' => (string) ($this->name ?? ''),
            'total_units' => (float) ($this->total_units ?? 0),
            'total_revenue' => (float) ($this->total_revenue ?? 0),
        ];
    }
}
