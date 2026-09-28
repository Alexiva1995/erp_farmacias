<?php

declare(strict_types=1);

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpiryReportResource extends JsonResource
{
    /**
     * Transformar el recurso en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kpis'          => $this->resource['kpis'] ?? [],
            'horizon'       => $this->resource['horizon'] ?? [],
            'loss_analysis' => $this->resource['loss_analysis'] ?? [],
            'overstock'     => $this->resource['overstock'] ?? [],
        ];
    }
}
