<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'supplier_id'             => $this->supplier_id ?? null,
            'supplier_name'           => $this->supplier_name ?? ($this->relationLoaded('supplier') ? $this->supplier?->name : null),
            'phone'                   => $this->phone ?? ($this->relationLoaded('supplier') ? $this->supplier?->sales_phone : null),
            'status'                  => is_object($this->status) ? $this->status->value : (int) $this->status,
            'total_quantity'          => (float) ($this->total_quantity ?? 0),
            'total_amount'            => (float) ($this->total_amount ?? 0),
            'order_date'              => $this->order_date ? (is_string($this->order_date) ? $this->order_date : $this->order_date->toDateTimeString()) : null,
            'tentative_delivery_date' => $this->tentative_delivery_date ? (is_string($this->tentative_delivery_date) ? $this->tentative_delivery_date : $this->tentative_delivery_date->toDateString()) : null,
            'hash_token'              => $this->hash_token ?? null,
            'created_at'              => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
