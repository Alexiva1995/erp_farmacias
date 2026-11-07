<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterSupplierCatalogResource extends JsonResource
{
    /**
     * Transforma el recurso a un arreglo asociativo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'master_id' => $this['master_id'] ?? null,
            'name' => $this['name'] ?? '',
            'social_reason' => $this['social_reason'] ?? '',
            'rif' => $this['rif'] ?? '',
            'type' => $this['type'] ?? 'drogueria',
            'business_types' => is_string($this['business_types'] ?? null) ? (json_decode($this['business_types'], true) ?: []) : ($this['business_types'] ?? []),
            'integration_type' => $this['integration_type'] ?? 'file',
            'has_excel_structure' => (bool) ($this['has_excel_structure'] ?? false),
            'excel_columns_summary' => $this['excel_columns_summary'] ?? [],
            'is_installed' => (bool) ($this['is_installed'] ?? false),
            'is_active' => (bool) ($this['is_active'] ?? false),
            'tenant_supplier_id' => $this['tenant_supplier_id'] ?? null,
            'configured_email' => $this['configured_email'] ?? null,
            'credit_days' => (int) ($this['credit_days'] ?? 0),
        ];
    }
}
