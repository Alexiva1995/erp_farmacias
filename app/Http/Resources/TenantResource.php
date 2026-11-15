<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GeneralSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isRemote = (bool) ($this->is_remote ?? false);
        $vertical = $this->vertical;

        // Si la vertical no está guardada en la tabla central de tenants y no es remoto, resolverla desde la BD del tenant
        if (empty($vertical) && !$isRemote) {
            try {
                $dbName = $this->database()->getName();
                $dbExists = false;
                try {
                    $dbExists = $this->database()->manager()->databaseExists($dbName);
                } catch (\Throwable) {
                    $dbExists = false;
                }

                if ($dbExists) {
                    $this->run(function () use (&$vertical) {
                        $setting = GeneralSetting::select('business_type')->first();
                        if ($setting?->business_type) {
                            $vertical = $setting->business_type;
                        }
                    });
                }
            } catch (\Throwable $e) {
                // Silencioso ante excepciones de conexión
            }
        }

        $domains = $this->whenLoaded('domains', function () {
            if ($this->domains->isEmpty() && !empty($this->id)) {
                return [
                    [
                        'id' => 0,
                        'domain' => "{$this->id}.tovaerp.com",
                    ],
                ];
            }

            return $this->domains->map(fn($d) => [
                'id' => $d->id,
                'domain' => $d->domain,
            ])->values()->all();
        });

        $plan = $this->plan ?? 'basic';
        $defaultPrice = $plan === 'pro' ? 49.0 : ($plan === 'enterprise' ? 499.0 : 29.0);

        $subscription = null;
        try {
            $centralConn = (string) config('tenancy.database.central_connection', config('database.default'));
            if (\Illuminate\Support\Facades\Schema::connection($centralConn)->hasTable('saas_subscriptions')) {
                $subscription = \App\Models\SaasSubscription::where('tenant_id', (string) $this->id)->first();
            }
        } catch (\Throwable) {
            $subscription = null;
        }

        $statusVal = $subscription?->status ?? 'active';
        $statusEnum = \App\Enums\SaasSubscriptionStatusEnum::tryFrom((string) $statusVal) ?? \App\Enums\SaasSubscriptionStatusEnum::ACTIVE;
        $billingDate = $subscription?->current_period_ends_at?->format('Y-m-d') ?? $this->billing_date ?? null;

        $daysRemaining = null;
        if ($billingDate) {
            try {
                $daysRemaining = (int) \Carbon\Carbon::today()->diffInDays(\Carbon\Carbon::parse((string) $billingDate)->startOfDay(), false);
            } catch (\Throwable) {
                $daysRemaining = null;
            }
        }

        return [
            'id' => $this->id,
            'company_name' => $this->company_name ?? $this->id,
            'database' => $isRemote ? null : $this->database()->getName(),
            'is_remote' => $isRemote,
            'vertical' => $vertical ?: ($subscription?->vertical ?: 'pharmacy'),
            'plan_id' => $this->plan_id ?? $subscription?->plan_code ?? $plan,
            'plan' => $subscription?->plan_code ?: $plan,
            'billing_cycle' => $subscription?->billing_cycle ?: 'monthly',
            'monthly_price' => isset($this->monthly_price) ? (float) $this->monthly_price : ($subscription ? (float) $subscription->price_agreed_usd : $defaultPrice),
            'billing_date' => $billingDate,
            'grace_period_ends_at' => $subscription?->grace_period_ends_at?->format('Y-m-d'),
            'days_remaining' => $daysRemaining,
            'license_key' => $subscription?->license_key,
            'subscription_id' => $subscription?->id,
            'subscription_status' => $statusEnum->value,
            'subscription_status_label' => $statusEnum->label(),
            'subscription_status_color' => $statusEnum->color(),
            'can_operate' => $statusEnum->canOperate(),
            'contact_name' => $subscription?->contact_name,
            'contact_email' => $subscription?->contact_email,
            'contact_phone' => $subscription?->contact_phone ?? $this->branch_phone,
            'seller_name' => $subscription?->seller_name,
            'notes' => $subscription?->notes,
            'max_users' => (int) ($subscription?->max_users ?? 5),
            'parent_tenant_id' => $this->parent_tenant_id,
            'branch_code' => $this->branch_code,
            'can_create_branches' => (bool) ($this->can_create_branches ?? false),
            'max_branches' => (int) ($this->max_branches ?? $subscription?->max_branches ?? 5),
            'is_branch_active' => (bool) ($this->is_branch_active ?? true),
            'domains' => $domains,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
