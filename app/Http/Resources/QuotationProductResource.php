<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'quotation_id' => $this->quotation_id,
            'product_id'   => $this->product_id,
            'dish_id'      => $this->dish_id,
            'units'        => (int) ($this->units ?? 1),
            'product'      => $this->relationLoaded('product') && $this->product ? [
                'id'                => $this->product->id,
                'name'              => $this->product->name,
                'sale_price'        => (float) ($this->product->sale_price ?? 0),
                'active_ingredient' => $this->product->active_ingredient ?? null,
                'barcode'           => $this->product->barcode ?? null,
            ] : null,
            'dish'         => $this->relationLoaded('dish') && $this->dish ? [
                'id'               => $this->dish->id,
                'name'             => $this->dish->name,
                'designated_price' => (float) ($this->dish->designated_price ?? 0),
            ] : null,
        ];
    }
}
