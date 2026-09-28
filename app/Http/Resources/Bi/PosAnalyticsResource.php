<?php

declare(strict_types=1);

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosAnalyticsResource extends JsonResource
{
    /**
     * Transformar el recurso en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kpis' => [
                'completed_sales' => (int) ($this->resource['kpis']['completed_sales'] ?? 0),
                'abandoned_sales' => (int) ($this->resource['kpis']['abandoned_sales'] ?? 0),
                'quotations_generated' => (int) ($this->resource['kpis']['quotations_generated'] ?? 0),
                'conversion_rate' => (float) ($this->resource['kpis']['conversion_rate'] ?? 0.0),
                'avg_ticket' => (float) ($this->resource['kpis']['avg_ticket'] ?? 0.0),
                'avg_daily_sales' => (float) ($this->resource['kpis']['avg_daily_sales'] ?? 0.0),
                'total_revenue' => (float) ($this->resource['kpis']['total_revenue'] ?? 0.0),
                'cross_selling_count' => (int) ($this->resource['kpis']['cross_selling_count'] ?? 0),
                'cross_selling_rate' => (float) ($this->resource['kpis']['cross_selling_rate'] ?? 0.0),
                'total_units' => (float) ($this->resource['kpis']['total_units'] ?? 0.0),
                'units_per_transaction' => (float) ($this->resource['kpis']['units_per_transaction'] ?? 0.0),
                'discount_total' => (float) ($this->resource['kpis']['discount_total'] ?? 0.0),
                'operational_days' => (int) ($this->resource['kpis']['operational_days'] ?? 0),
            ],
            'charts' => [
                'daily_trend' => $this->resource['charts']['daily_trend'] ?? [
                    'series' => [],
                    'categories' => [],
                ],
                'daily_focus' => $this->resource['charts']['daily_focus'] ?? [
                    'series' => [],
                    'categories' => [],
                ],
                'hourly_distribution' => $this->resource['charts']['hourly_distribution'] ?? [
                    'series' => [],
                ],
            ],
            'segmentation' => [
                'units' => [
                    'labels' => $this->resource['segmentation']['units']['labels'] ?? [],
                    'series' => $this->resource['segmentation']['units']['series'] ?? [],
                ],
                'monetary' => [
                    'labels' => $this->resource['segmentation']['monetary']['labels'] ?? [],
                    'series' => $this->resource['segmentation']['monetary']['series'] ?? [],
                ],
            ],
            'filter_options' => $this->resource['filter_options'] ?? [
                'sellers' => [],
            ],
        ];
    }
}
