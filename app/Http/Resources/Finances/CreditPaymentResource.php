<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return [
                'id' => $this->resource['id'] ?? null,
                'amount' => (float) ($this->resource['amount'] ?? 0),
                'method' => $this->resource['method'] ?? '',
                'currency' => $this->resource['currency'] ?? 'USD',
                'reference' => $this->resource['reference'] ?? 'N/A',
                'date' => $this->resource['date'] ?? null,
                'seller' => $this->resource['seller'] ?? 'N/A',
                'client' => $this->resource['client'] ?? 'N/A',
            ];
        }

        return [
            'id' => $this->id,
            'amount' => (float) ($this->amount ?? 0),
            'method' => $this->method ?? '',
            'currency' => $this->currency ?? 'USD',
            'reference' => $this->reference ?? 'N/A',
            'date' => $this->payment_date ?? $this->date ?? null,
            'seller' => $this->seller ?? 'N/A',
            'client' => $this->client ?? 'N/A',
        ];
    }
}
