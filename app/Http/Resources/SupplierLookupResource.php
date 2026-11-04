<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierLookupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'exists_locally'  => (bool) ($this->resource['exists_locally'] ?? false),
            'found_in_master' => (bool) ($this->resource['found_in_master'] ?? false),
            'supplier'        => $this->resource['supplier'] ?? null,
        ];
    }
}
