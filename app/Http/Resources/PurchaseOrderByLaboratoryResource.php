<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderByLaboratoryResource extends JsonResource
{
    /**
     * Transforma el recurso de laboratorio en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'laboratory_id' => $this->resource['laboratory_id'] ?? $this->resource->laboratory_id ?? null,
            'laboratory_name' => $this->resource['laboratory_name'] ?? $this->resource->laboratory_name ?? 'Sin Laboratorio',
            'total_skus' => (int) ($this->resource['total_skus'] ?? $this->resource->total_skus ?? 0),
            'total_units' => (int) ($this->resource['total_units'] ?? $this->resource->total_units ?? 0),
            'total_amount_usd' => (float) ($this->resource['total_amount_usd'] ?? $this->resource->total_amount_usd ?? 0.0),
        ];
    }
}
