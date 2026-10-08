<?php

declare(strict_types=1);

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPayablesPreviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'summary'                      => $this['summary'] ?? [],
            'matched_suppliers'            => $this['matched_suppliers'] ?? [],
            'new_suppliers'                => $this['new_suppliers'] ?? [],
            'existing_suppliers_directory' => $this['existing_suppliers_directory'] ?? [],
        ];
    }
}
