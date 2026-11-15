<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Enums\SaasBillingCycleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaasPlanResource extends JsonResource
{
    /**
     * Transformar el recurso en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cycle = SaasBillingCycleEnum::tryFrom((string) $this->billing_cycle) ?? SaasBillingCycleEnum::MONTHLY;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'billing_cycle' => $cycle->value,
            'billing_cycle_label' => $cycle->label(),
            'price_usd' => (float) $this->price_usd,
            'setup_fee_usd' => (float) ($this->setup_fee_usd ?? 0),
            'trial_days' => (int) ($this->trial_days ?? 14),
            'grace_days' => (int) ($this->grace_days ?? 5),
            'max_users' => (int) ($this->max_users ?? 5),
            'max_branches' => (int) ($this->max_branches ?? 1),
            'included_modules' => $this->included_modules ?? [],
            'features' => $this->features ?? [],
            'is_active' => (bool) $this->is_active,
            'subscriptions_count' => $this->whenCounted('subscriptions', (int) ($this->subscriptions_count ?? 0)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
