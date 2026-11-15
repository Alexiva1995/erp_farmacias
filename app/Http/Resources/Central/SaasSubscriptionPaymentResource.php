<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaasSubscriptionPaymentResource extends JsonResource
{
    /**
     * Transformar el recurso en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'saas_subscription_id' => $this->saas_subscription_id,
            'tenant_id' => $this->tenant_id,
            'company_name' => $this->whenLoaded('subscription', fn () => $this->subscription?->company_name),
            'plan_code' => $this->plan_code,
            'receipt_number' => $this->receipt_number,
            'amount_usd' => (float) $this->amount_usd,
            'exchange_rate' => (float) ($this->exchange_rate ?? 1),
            'amount_paid_currency' => (float) ($this->amount_paid_currency ?? $this->amount_usd),
            'currency' => $this->currency ?: 'USD',
            'payment_method' => $this->payment_method ?: 'transfer',
            'reference_number' => $this->reference_number,
            'status' => $this->status ?: 'approved',
            'period_starts_at' => $this->period_starts_at?->format('Y-m-d'),
            'period_ends_at' => $this->period_ends_at?->format('Y-m-d'),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'reported_by' => $this->reported_by,
            'approved_by' => $this->approved_by,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
