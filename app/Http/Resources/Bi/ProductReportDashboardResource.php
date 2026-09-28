<?php

declare(strict_types=1);

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para estandarizar la respuesta del dashboard de BI de Productos.
 */
class ProductReportDashboardResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'quadrant1' => [
                'top_volume' => $this->resource['quadrant1']['top_volume'] ?? [],
                'top_revenue' => $this->resource['quadrant1']['top_revenue'] ?? [],
                'top_margin' => $this->resource['quadrant1']['top_margin'] ?? [],
                'lab_ranking' => $this->resource['quadrant1']['lab_ranking'] ?? [],
                'pareto' => $this->resource['quadrant1']['pareto'] ?? ['percent' => 0],
            ],
            'quadrant2' => [
                'abc' => $this->resource['quadrant2']['abc'] ?? [],
                'cross_selling' => $this->resource['quadrant2']['cross_selling'] ?? null,
            ],
            'quadrant4' => [
                'out_of_stock' => (int) ($this->resource['quadrant4']['out_of_stock'] ?? 0),
                'critical_stock' => (int) ($this->resource['quadrant4']['critical_stock'] ?? 0),
                'avg_inventory_days' => (float) ($this->resource['quadrant4']['avg_inventory_days'] ?? 0),
                'estimated_30d_demand' => (float) ($this->resource['quadrant4']['estimated_30d_demand'] ?? 0),
            ],
        ];
    }
}
