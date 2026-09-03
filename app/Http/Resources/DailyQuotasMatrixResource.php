<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyQuotasMatrixResource extends JsonResource
{
    /**
     * Transforma la matriz de cuotas diarias en un arreglo estandarizado.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'month' => (int) ($this->resource['month'] ?? now()->month),
            'year' => (int) ($this->resource['year'] ?? now()->year),
            'daily_quota' => (int) ($this->resource['daily_quota'] ?? 50),
            'employees' => $this->resource['employees'] ?? [],
            'data' => $this->resource['data'] ?? [],
            'summary' => [
                'total_month_counts' => (int) ($this->resource['summary']['total_month_counts'] ?? 0),
                'active_days' => (int) ($this->resource['summary']['active_days'] ?? 0),
                'daily_average' => (float) ($this->resource['summary']['daily_average'] ?? 0.0),
                'top_employee' => $this->resource['summary']['top_employee'] ?? null,
            ],
        ];
    }
}
