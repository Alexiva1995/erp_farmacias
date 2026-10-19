<?php

namespace App\Http\Resources\Tpv;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoomItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_detail' => $this->resource['order_detail'] ?? null,
            'order' => $this->resource['order'] ?? null,
            'product' => $this->resource['product'] ?? ($this->resource instanceof \App\Models\Product ? $this->resource : null),
        ];
    }
}
