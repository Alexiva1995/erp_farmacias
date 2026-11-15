<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Enums\SaasBillingCycleEnum;
use App\Enums\SaasSubscriptionStatusEnum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaasSubscriptionResource extends JsonResource
{
    /**
     * Transformar el recurso en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusEnum = SaasSubscriptionStatusEnum::tryFrom((string) $this->status) ?? SaasSubscriptionStatusEnum::ACTIVE;
        $cycleEnum = SaasBillingCycleEnum::tryFrom((string) $this->billing_cycle) ?? SaasBillingCycleEnum::MONTHLY;

        $daysRemaining = null;
        if ($this->current_period_ends_at) {
            $daysRemaining = (int) Carbon::today()->diffInDays(Carbon::parse($this->current_period_ends_at)->startOfDay(), false);
        }

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'domain' => $this->domain,
            'vertical' => $this->vertical ?: 'pharmacy',
            'saas_plan_id' => $this->saas_plan_id,
            'plan_code' => $this->plan_code ?: 'basic',
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name, strtoupper((string) ($this->plan_code ?: 'basic'))),
            'billing_cycle' => $cycleEnum->value,
            'billing_cycle_label' => $cycleEnum->label(),
            'license_key' => $this->license_key,
            'status' => $statusEnum->value,
            'status_label' => $statusEnum->label(),
            'status_color' => $statusEnum->color(),
            'can_operate' => $statusEnum->canOperate(),
            'price_agreed_usd' => (float) $this->price_agreed_usd,
            'mrr_equivalent_usd' => round((float) $this->price_agreed_usd / max(1, $cycleEnum->monthsCount()), 2),
            'starts_at' => $this->starts_at?->format('Y-m-d'),
            'trial_ends_at' => $this->trial_ends_at?->format('Y-m-d'),
            'current_period_ends_at' => $this->current_period_ends_at?->format('Y-m-d'),
            'grace_period_ends_at' => $this->grace_period_ends_at?->format('Y-m-d'),
            'days_remaining' => $daysRemaining,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'is_remote' => (bool) $this->is_remote,
            'auto_renew' => (bool) $this->auto_renew,
            'max_users' => (int) ($this->max_users ?? 5),
            'max_branches' => (int) ($this->max_branches ?? 1),
            'seller_name' => $this->seller_name,
            'notes' => $this->notes,
            'payments' => SaasSubscriptionPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
