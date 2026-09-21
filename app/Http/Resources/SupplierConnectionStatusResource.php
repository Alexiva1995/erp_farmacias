<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierConnectionStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->username ?? 'Sistema',
            'status' => $this->status,
            'message' => $this->message,
            'count_product' => (int) ($this->count_product ?? 0),
            'count_invoice' => (int) ($this->count_invoice ?? 0),
            'details' => $this->details ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_formatted' => $this->created_at ? $this->created_at->format('d/m/Y h:i A') : '—',
        ];
    }
}
