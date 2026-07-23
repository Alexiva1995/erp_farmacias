<?php

declare(strict_types=1);

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAnalyticsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kpis' => [
                'total_customers' => (int) ($this['kpis']['total_customers'] ?? 0),
                'repurchase_count' => (int) ($this['kpis']['repurchase_count'] ?? 0),
                'repurchase_rate' => (float) ($this['kpis']['repurchase_rate'] ?? 0),
                'avg_ltv' => (float) ($this['kpis']['avg_ltv'] ?? 0),
                'crr' => (float) ($this['kpis']['crr'] ?? 0),
                'churn_rate' => (float) ($this['kpis']['churn_rate'] ?? 0),
            ],
            'growth' => [
                'new_customers_daily' => $this['growth']['new_customers_daily'] ?? [],
            ],
            'segmentation' => $this['segmentation'] ?? [],
            'frequency' => empty($this['frequency']) ? (object) [] : (object) $this['frequency'],
            'cohorts' => $this['cohorts'] ?? [],
            'at_risk' => $this['at_risk'] ?? [],
        ];
    }
}
