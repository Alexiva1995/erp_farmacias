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
            'logo_url' => $this['logo_url'] ?? null,
            'social_reason' => $this['social_reason'] ?? '',
            'rif' => $this['rif'] ?? '',
            'address' => $this['address'] ?? null,
            'sales_phone' => $this['sales_phone'] ?? null,
            'collections_phone' => $this['collections_phone'] ?? null,
            'payment_email' => $this['payment_email'] ?? null,
            'type' => $this['type'] ?? 'drogueria',
            'business_types' => is_string($this['business_types'] ?? null) ? (json_decode($this['business_types'], true) ?: []) : ($this['business_types'] ?? []),
            'is_indexed' => (bool) ($this['is_indexed'] ?? false),
            'is_fiscal' => isset($this['is_fiscal']) ? (bool) $this['is_fiscal'] : true,
            'integration_type' => $this['integration_type'] ?? 'file',
            'allowed_connection_types' => $this['allowed_connection_types'] ?? ['file'],
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
