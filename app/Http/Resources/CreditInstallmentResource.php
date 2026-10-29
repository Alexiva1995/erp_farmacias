<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditInstallmentResource extends JsonResource
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
            'credit_id' => $this->credit_id,
            'installment_number' => $this->installment_number,
            'amount_usd' => (float) $this->amount_usd,
            'paid_amount_usd' => (float) $this->paid_amount_usd,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'paid_at' => $this->paid_at ? $this->paid_at->toIso8601String() : null,
            'payment_reference' => $this->payment_reference,
            'status' => $this->status,
            'client_name' => $this->credit?->client ? trim($this->credit->client->name . ' ' . ($this->credit->client->last_name ?? '')) : null,
            'cashea_order_reference' => $this->credit?->cashea_order_reference,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
