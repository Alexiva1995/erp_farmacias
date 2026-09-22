<?php

namespace App\Http\Resources\Islr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IslrSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gross_income' => (float) ($this['gross_income'] ?? 0),
            'deductions' => (float) ($this['deductions'] ?? 0),
            'non_deductible' => (float) ($this['non_deductible'] ?? 0),
            'net_income' => (float) ($this['net_income'] ?? 0),
            'ibg' => (float) ($this['ibg'] ?? 0),
            'costs' => (float) ($this['costs'] ?? 0),
            'withholdings' => (float) ($this['withholdings'] ?? 0),
            'year' => (int) ($this['year'] ?? now()->year),
            'currency' => 'VES',
            'calculated_at' => $this['calculated_at'] ?? now()->toISOString(),
            'monthly_breakdown' => $this['monthly_breakdown'] ?? [],
        ];
    }
}
