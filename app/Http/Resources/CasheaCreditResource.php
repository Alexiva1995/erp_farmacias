<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CasheaCreditResource extends JsonResource
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
            'client_id' => $this->client_id,
            'order_id' => $this->order_id,
            'client' => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'last_name' => $this->client->last_name,
                'identification' => $this->client->identification,
                'phone' => $this->client->phone,
            ] : null,
            'cashea_order_reference' => $this->cashea_order_reference,
            'credit_amount' => (float) $this->credit_amount,
            'pending_amount' => (float) $this->pending_amount,
            'initial_payment_usd' => (float) $this->initial_payment_usd,
            'installments_count' => (int) $this->installments_count,
            'credit_date' => $this->credit_date ? $this->credit_date->format('Y-m-d H:i') : null,
            'next_due_date' => $this->next_due_date ? $this->next_due_date->format('Y-m-d') : null,
            'status' => $this->status,
            'installments' => CreditInstallmentResource::collection($this->whenLoaded('installments')),
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
