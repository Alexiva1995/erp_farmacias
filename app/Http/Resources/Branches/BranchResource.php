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
        $isArr = is_array($this->resource);

        return [
            'id' => $isArr ? ($this->resource['id'] ?? null) : $this->id,
            'parent_tenant_id' => $isArr ? ($this->resource['parent_tenant_id'] ?? null) : $this->parent_tenant_id,
            'company_name' => $isArr ? ($this->resource['company_name'] ?? null) : $this->company_name,
            'branch_code' => $isArr ? ($this->resource['branch_code'] ?? null) : $this->branch_code,
            'branch_address' => $isArr ? ($this->resource['branch_address'] ?? null) : $this->branch_address,
            'branch_phone' => $isArr ? ($this->resource['branch_phone'] ?? null) : $this->branch_phone,
            'is_matriz' => $isArr ? ($this->resource['is_matriz'] ?? false) : ($this->parent_tenant_id === null),
            'is_branch_active' => (bool) ($isArr ? ($this->resource['is_branch_active'] ?? true) : ($this->is_branch_active ?? true)),
            'can_create_branches' => (bool) ($isArr ? ($this->resource['can_create_branches'] ?? false) : ($this->can_create_branches ?? false)),
            'max_branches' => (int) ($isArr ? ($this->resource['max_branches'] ?? 5) : ($this->max_branches ?? 5)),
            'domain' => $isArr ? ($this->resource['domain'] ?? null) : null,
            'url' => $isArr ? ($this->resource['url'] ?? null) : null,
            'database' => $isArr ? ($this->resource['database'] ?? null) : null,
            'created_at' => $isArr ? ($this->resource['created_at'] ?? null) : $this->created_at?->toIso8601String(),
        ];
    }
}
