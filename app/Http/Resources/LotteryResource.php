<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LotteryResource extends JsonResource
{
    /**
     * Transformar la orden en un ticket de sorteo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->invoice_number ?? "TICK-{$this->id}",
            'client_id' => $this->client_id,
            'client_name' => $this->client ? trim("{$this->client->name} {$this->client->last_name}") : ($this->customer_name ?? 'Cliente Genérico'),
            'client_identification' => $this->client?->identification ?? $this->customer_identification ?? 'N/A',
            'client_phone' => $this->client?->phone ?? 'N/A',
            'total_amount' => (float) ($this->total_amount ?? $this->total ?? 0),
            'date' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
