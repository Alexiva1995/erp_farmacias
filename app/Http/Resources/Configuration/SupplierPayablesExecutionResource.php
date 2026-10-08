<?php

declare(strict_types=1);

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPayablesExecutionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'suppliers_created'          => (int) ($this['suppliers_created'] ?? 0),
            'suppliers_updated'          => (int) ($this['suppliers_updated'] ?? 0),
            'invoices_created'           => (int) ($this['invoices_created'] ?? 0),
            'invoices_skipped_duplicate' => (int) ($this['invoices_skipped_duplicate'] ?? 0),
            'total_amount_usd'           => (float) ($this['total_amount_usd'] ?? 0.0),
            'total_amount_ves'           => (float) ($this['total_amount_ves'] ?? 0.0),
        ];
    }
}
