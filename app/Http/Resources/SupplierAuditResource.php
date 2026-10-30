<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierAuditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'period'    => $this->resource['period'] ?? [],
            'supplier'  => $this->resource['supplier'] ?? [],
            'kpis'      => $this->resource['kpis'] ?? [],
            'tenants'   => $this->resource['tenants'] ?? [],
            'suppliers' => $this->resource['suppliers'] ?? [],
        ];
    }
}
