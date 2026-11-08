<?php

declare(strict_types=1);

namespace App\Http\Resources\Branches;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? $this->id,
            'parent_tenant_id' => $this->resource['parent_tenant_id'] ?? $this->parent_tenant_id,
            'company_name' => $this->resource['company_name'] ?? $this->company_name,
            'branch_code' => $this->resource['branch_code'] ?? $this->branch_code,
            'branch_address' => $this->resource['branch_address'] ?? $this->branch_address,
            'branch_phone' => $this->resource['branch_phone'] ?? $this->branch_phone,
            'is_matriz' => $this->resource['is_matriz'] ?? ($this->parent_tenant_id === null),
            'is_branch_active' => (bool) ($this->resource['is_branch_active'] ?? $this->is_branch_active ?? true),
            'can_create_branches' => (bool) ($this->resource['can_create_branches'] ?? $this->can_create_branches ?? false),
            'max_branches' => (int) ($this->resource['max_branches'] ?? $this->max_branches ?? 5),
            'domain' => $this->resource['domain'] ?? null,
            'url' => $this->resource['url'] ?? null,
            'database' => $this->resource['database'] ?? null,
            'created_at' => $this->resource['created_at'] ?? $this->created_at?->toIso8601String(),
        ];
    }
}
