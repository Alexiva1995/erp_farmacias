<?php

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryCyclicReportResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kpis' => [
                'eri' => (float)($this->resource['kpis']['eri'] ?? 100),
                'net_loss' => (float)($this->resource['kpis']['net_loss'] ?? 0),
                'error_rate' => (float)($this->resource['kpis']['error_rate'] ?? 0),
                'total_missing_units' => (int)($this->resource['kpis']['total_missing_units'] ?? 0),
                'total_surplus_units' => (int)($this->resource['kpis']['total_surplus_units'] ?? 0),
                'total_counted_skus' => (int)($this->resource['kpis']['total_counted_skus'] ?? 0),
            ],
            'trends' => [
                'series' => $this->resource['trends']['series'] ?? [],
                'categories' => $this->resource['trends']['categories'] ?? [],
                'financial_series' => $this->resource['trends']['financial_series'] ?? [],
            ],
            'deviations' => [
                'top_missing' => [
                    'series' => $this->resource['deviations']['top_missing']['series'] ?? [],
                    'categories' => $this->resource['deviations']['top_missing']['categories'] ?? [],
                ],
                'top_surplus' => [
                    'series' => $this->resource['deviations']['top_surplus']['series'] ?? [],
                    'categories' => $this->resource['deviations']['top_surplus']['categories'] ?? [],
                ],
                'categories' => [
                    'series' => $this->resource['deviations']['categories']['series'] ?? [],
                    'labels' => $this->resource['deviations']['categories']['labels'] ?? [],
                ],
            ],
            'substitutions' => array_map(function ($sub) {
                return [
                    'category' => (string)$sub['category'],
                    'product_a' => (string)$sub['product_a'],
                    'discrepancy_a' => (int)$sub['discrepancy_a'],
                    'product_b' => (string)$sub['product_b'],
                    'discrepancy_b' => (int)$sub['discrepancy_b'],
                    'confidence' => (string)$sub['confidence'],
                ];
            }, $this->resource['substitutions'] ?? []),
        ];
    }
}
