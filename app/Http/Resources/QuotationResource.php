<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'currency'    => $this->currency,
            'tax_exempt'  => (int) ($this->tax_exempt ?? 0),
            'vat'         => (float) ($this->vat ?? 0),
            'total'       => (float) ($this->total ?? 0),
            'created_by'  => $this->created_by,
            'client_id'   => $this->client_id,
            'client'      => $this->relationLoaded('client') && $this->client ? [
                'id'             => $this->client->id,
                'name'           => $this->client->name,
                'identification' => $this->client->identification,
                'phone'          => $this->client->phone ?? null,
                'email'          => $this->client->email ?? null,
            ] : null,
            'creator'     => $this->relationLoaded('creator') && $this->creator ? [
                'id'       => $this->creator->id,
                'name'     => $this->creator->name,
                'username' => $this->creator->username,
            ] : null,
            'products'    => $this->relationLoaded('products') ? QuotationProductResource::collection($this->products) : [],
            'created_at'  => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
