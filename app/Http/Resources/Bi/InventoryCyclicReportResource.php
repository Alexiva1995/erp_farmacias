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
                'financial_eri' => (float)($this->resource['kpis']['financial_eri'] ?? 100),
                'net_loss' => (float)($this->resource['kpis']['net_loss'] ?? 0),
                'missing_loss_value' => (float)($this->resource['kpis']['missing_loss_value'] ?? 0),
                'surplus_gain_value' => (float)($this->resource['kpis']['surplus_gain_value'] ?? 0),
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
                    'impact_values' => $this->resource['deviations']['top_missing']['impact_values'] ?? [],
                    'categories' => $this->resource['deviations']['top_missing']['categories'] ?? [],
                ],
                'top_surplus' => [
                    'series' => $this->resource['deviations']['top_surplus']['series'] ?? [],
                    'impact_values' => $this->resource['deviations']['top_surplus']['impact_values'] ?? [],
                    'categories' => $this->resource['deviations']['top_surplus']['categories'] ?? [],
                ],
                'categories' => [
                    'series' => $this->resource['deviations']['categories']['series'] ?? [],
                    'financial_series' => $this->resource['deviations']['categories']['financial_series'] ?? [],
                    'labels' => $this->resource['deviations']['categories']['labels'] ?? [],
                ],
            ],
            'substitutions' => array_map(function ($sub) {
                return [
                    'category' => (string)($sub['category'] ?? ''),
                    'product_a' => (string)($sub['product_a'] ?? ''),
                    'active_ingredient_a' => (string)($sub['active_ingredient_a'] ?? ''),
                    'discrepancy_a' => (int)($sub['discrepancy_a'] ?? 0),
                    'product_b' => (string)($sub['product_b'] ?? ''),
                    'active_ingredient_b' => (string)($sub['active_ingredient_b'] ?? ''),
                    'discrepancy_b' => (int)($sub['discrepancy_b'] ?? 0),
                    'confidence' => (string)($sub['confidence'] ?? ''),
                    'match_reason' => (string)($sub['match_reason'] ?? ''),
                ];
            }, $this->resource['substitutions'] ?? []),
        ];
    }
}
