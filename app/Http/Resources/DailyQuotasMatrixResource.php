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
        $rows = array_map(function ($row) {
            if (isset($row['users']) && is_array($row['users'])) {
                $row['users'] = (object) $row['users'];
            }
            return $row;
        }, $this->resource['data'] ?? []);

        return [
            'month' => (int) ($this->resource['month'] ?? now()->month),
            'year' => (int) ($this->resource['year'] ?? now()->year),
            'daily_quota' => (int) ($this->resource['daily_quota'] ?? 50),
            'employees' => $this->resource['employees'] ?? [],
            'data' => $rows,
            'summary' => [
                'total_month_counts' => (int) ($this->resource['summary']['total_month_counts'] ?? 0),
                'active_days' => (int) ($this->resource['summary']['active_days'] ?? 0),
                'daily_average' => (float) ($this->resource['summary']['daily_average'] ?? 0.0),
                'top_employee' => $this->resource['summary']['top_employee'] ?? null,
            ],
        ];
    }
}
