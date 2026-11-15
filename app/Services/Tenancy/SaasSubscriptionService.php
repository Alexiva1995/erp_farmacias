<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Contracts\Repositories\SaasSubscriptionRepositoryInterface;
use App\Contracts\Tenancy\SaasSubscriptionServiceInterface;
use App\Enums\SaasBillingCycleEnum;
use App\Enums\SaasSubscriptionStatusEnum;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\SaasSubscriptionPayment;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SaasSubscriptionService implements SaasSubscriptionServiceInterface
{
    public function __construct(
        protected SaasSubscriptionRepositoryInterface $repository
    ) {}

    /**
     * @inheritDoc
     */
    public function getPlans(bool $onlyActive = false): Collection
    {
        if (!$this->hasSaasTables()) {
            return new Collection();
        }

        return $this->repository->getAllPlans($onlyActive);
    }

    /**
     * @inheritDoc
     */
    public function savePlan(array $data, ?int $id = null): SaasPlan
    {
        $data['code'] = strtolower(trim((string) $data['code']));
        $data['setup_fee_usd'] = (float) ($data['setup_fee_usd'] ?? 0);
        $data['trial_days'] = (int) ($data['trial_days'] ?? 14);
        $data['grace_days'] = (int) ($data['grace_days'] ?? 5);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $this->repository->savePlan($data, $id);
    }

    /**
     * @inheritDoc
     */
    public function deletePlan(int $id): bool
    {
        return $this->repository->deletePlan($id);
    }

    /**
     * @inheritDoc
     */
    public function syncTenantSubscription(Tenant $tenant, array $overrides = []): SaasSubscription
    {
        $existing = $this->repository->findByTenantId((string) $tenant->id);

        $planCode = strtolower((string) ($overrides['plan'] ?? $overrides['plan_code'] ?? $tenant->plan ?? $existing?->plan_code ?? 'basic'));
        $planModel = $this->repository->findPlanByCodeOrId($planCode);

        $defaultPrice = $planModel ? (float) $planModel->price_usd : ($planCode === 'pro' ? 49.0 : 29.0);
        $priceAgreed = isset($overrides['monthly_price'])
            ? (float) $overrides['monthly_price']
            : (isset($overrides['price_agreed_usd'])
                ? (float) $overrides['price_agreed_usd']
                : (isset($tenant->monthly_price) ? (float) $tenant->monthly_price : ($existing?->price_agreed_usd ?? $defaultPrice)));

        $billingCycle = (string) ($overrides['billing_cycle'] ?? $existing?->billing_cycle ?? $planModel?->billing_cycle ?? SaasBillingCycleEnum::MONTHLY->value);

        $periodEnd = $overrides['billing_date']
            ?? $overrides['current_period_ends_at']
            ?? $tenant->billing_date
            ?? ($existing?->current_period_ends_at ? $existing->current_period_ends_at->format('Y-m-d') : Carbon::today()->addMonth()->format('Y-m-d'));

        $graceDays = (int) ($planModel?->grace_days ?? 5);
        $graceEnd = $overrides['grace_period_ends_at']
            ?? ($periodEnd ? Carbon::parse((string) $periodEnd)->addDays($graceDays)->format('Y-m-d') : null);

        $status = (string) ($overrides['status'] ?? $existing?->status ?? SaasSubscriptionStatusEnum::ACTIVE->value);

        $firstDomain = $overrides['domain']
            ?? ($tenant->relationLoaded('domains') ? $tenant->domains->first()?->domain : null)
            ?? $existing?->domain
            ?? "{$tenant->id}.tovaerp.com";

        $licenseKey = $existing?->license_key ?: $this->generateUniqueLicenseKey((string) $tenant->id, $planCode);

        $maxUsers = (int) ($overrides['max_users'] ?? $existing?->max_users ?? $planModel?->max_users ?? 5);
        $maxBranches = (int) ($overrides['max_branches'] ?? $tenant->max_branches ?? $existing?->max_branches ?? $planModel?->max_branches ?? 1);

        $attributes = [
            'company_name' => (string) ($overrides['company_name'] ?? $tenant->company_name ?? $tenant->id),
            'contact_name' => $overrides['contact_name'] ?? $overrides['admin_name'] ?? $existing?->contact_name,
            'contact_email' => $overrides['contact_email'] ?? $overrides['admin_email'] ?? $existing?->contact_email,
            'contact_phone' => $overrides['contact_phone'] ?? $tenant->branch_phone ?? $existing?->contact_phone,
            'domain' => $firstDomain,
            'vertical' => (string) ($overrides['vertical'] ?? $tenant->vertical ?? $existing?->vertical ?? 'pharmacy'),
            'saas_plan_id' => $planModel?->id ?? $existing?->saas_plan_id,
            'plan_code' => $planCode,
            'billing_cycle' => $billingCycle,
            'license_key' => $licenseKey,
            'status' => $status,
            'price_agreed_usd' => $priceAgreed,
            'starts_at' => $existing?->starts_at ?? ($tenant->created_at ? $tenant->created_at->format('Y-m-d') : Carbon::today()->format('Y-m-d')),
            'current_period_ends_at' => $periodEnd,
            'grace_period_ends_at' => $graceEnd,
            'suspended_at' => $status === SaasSubscriptionStatusEnum::SUSPENDED->value ? ($existing?->suspended_at ?? now()) : null,
            'is_remote' => (bool) ($overrides['is_remote'] ?? $tenant->is_remote ?? $existing?->is_remote ?? false),
            'max_users' => $maxUsers,
            'max_branches' => $maxBranches,
            'seller_name' => $overrides['seller_name'] ?? $existing?->seller_name,
            'notes' => $overrides['notes'] ?? $existing?->notes,
        ];

        $subscription = $this->repository->upsertForTenant((string) $tenant->id, $attributes);

        $this->flushTenantSubscriptionCache((string) $tenant->id);

        return $subscription;
    }

    /**
     * @inheritDoc
     */
    public function getDashboardMetrics(): array
    {
        if (!$this->hasSaasTables()) {
            return [
                'mrr_usd' => 0.0,
                'arr_usd' => 0.0,
                'collected_this_month_usd' => 0.0,
                'pending_approvals_count' => 0,
                'total_subscriptions' => 0,
                'active_count' => 0,
                'trial_count' => 0,
                'past_due_count' => 0,
                'suspended_count' => 0,
                'expiring_soon_count' => 0,
                'by_vertical' => [],
                'by_plan' => [],
            ];
        }

        // Asegurar sincronización de tenants existentes hacia saas_subscriptions
        if (Schema::hasTable('tenants')) {
            $tenants = Tenant::with('domains')->get();
            foreach ($tenants as $tenant) {
                $this->syncTenantSubscription($tenant);
            }
        }

        $subscriptions = $this->repository->getAllSubscriptions();

        $mrrUsd = 0.0;
        $activeCount = 0;
        $trialCount = 0;
        $pastDueCount = 0;
        $suspendedCount = 0;
        $expiringSoonCount = 0;
        $byVertical = [];
        $byPlan = [];

        $today = Carbon::today();
        $inSevenDays = Carbon::today()->addDays(7);

        foreach ($subscriptions as $sub) {
            $status = (string) $sub->status;
            $cycle = SaasBillingCycleEnum::tryFrom((string) $sub->billing_cycle) ?? SaasBillingCycleEnum::MONTHLY;

            if (in_array($status, [SaasSubscriptionStatusEnum::ACTIVE->value, SaasSubscriptionStatusEnum::PAST_DUE->value], true)) {
                $mrrUsd += (float) $sub->price_agreed_usd / max(1, $cycle->monthsCount());
            }

            if ($status === SaasSubscriptionStatusEnum::ACTIVE->value) {
                $activeCount++;
            } elseif ($status === SaasSubscriptionStatusEnum::TRIAL->value) {
                $trialCount++;
            } elseif ($status === SaasSubscriptionStatusEnum::PAST_DUE->value) {
                $pastDueCount++;
            } elseif ($status === SaasSubscriptionStatusEnum::SUSPENDED->value) {
                $suspendedCount++;
            }

            if ($sub->current_period_ends_at) {
                $endDate = Carbon::parse($sub->current_period_ends_at)->startOfDay();
                if ($endDate->betweenIncluded($today, $inSevenDays) && $status !== SaasSubscriptionStatusEnum::CANCELLED->value) {
                    $expiringSoonCount++;
                }
            }

            $vKey = (string) ($sub->vertical ?: 'pharmacy');
            $byVertical[$vKey] = ($byVertical[$vKey] ?? 0) + 1;

            $pKey = (string) ($sub->plan_code ?: 'basic');
            $byPlan[$pKey] = ($byPlan[$pKey] ?? 0) + 1;
        }

        $startOfMonth = Carbon::now()->startOfMonth();
        $collectedThisMonth = (float) SaasSubscriptionPayment::query()
            ->where('status', 'approved')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('amount_usd');

        $pendingApprovalsCount = (int) SaasSubscriptionPayment::query()
            ->where('status', 'pending')
            ->count();

        return [
            'mrr_usd' => round($mrrUsd, 2),
            'arr_usd' => round($mrrUsd * 12, 2),
            'collected_this_month_usd' => round($collectedThisMonth, 2),
            'pending_approvals_count' => $pendingApprovalsCount,
            'total_subscriptions' => $subscriptions->count(),
            'active_count' => $activeCount,
            'trial_count' => $trialCount,
            'past_due_count' => $pastDueCount,
            'suspended_count' => $suspendedCount,
            'expiring_soon_count' => $expiringSoonCount,
            'by_vertical' => $byVertical,
            'by_plan' => $byPlan,
        ];
    }

    /**
     * @inheritDoc
     */
    public function registerPaymentAndRenew(array $data, ?int $userId = null): SaasSubscriptionPayment
    {
        return DB::transaction(function () use ($data, $userId) {
            $subscription = null;

            if (!empty($data['saas_subscription_id'])) {
                $subscription = $this->repository->findById((int) $data['saas_subscription_id']);
            }

            if (!$subscription && !empty($data['tenant_id'])) {
                $subscription = $this->repository->findByTenantId((string) $data['tenant_id']);
                if (!$subscription) {
                    $tenant = Tenant::find((string) $data['tenant_id']);
                    if ($tenant) {
                        $subscription = $this->syncTenantSubscription($tenant);
                    }
                }
            }

            if (!$subscription) {
                throw new InvalidArgumentException('No se encontró la suscripción o empresa especificada.');
            }

            $autoApprove = (bool) ($data['auto_approve'] ?? true);
            $planCode = !empty($data['plan_code']) ? strtolower((string) $data['plan_code']) : $subscription->plan_code;
            $planModel = $this->repository->findPlanByCodeOrId($planCode);

            $cycle = SaasBillingCycleEnum::tryFrom((string) $subscription->billing_cycle) ?? SaasBillingCycleEnum::MONTHLY;
            $monthsToAdd = !empty($data['months_to_add']) ? (int) $data['months_to_add'] : $cycle->monthsCount();

            $currentEnd = $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture()
                ? Carbon::parse($subscription->current_period_ends_at)
                : Carbon::today();

            $newEnd = (clone $currentEnd)->addMonths($monthsToAdd);
            $receiptNumber = 'SAAS-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            $exchangeRate = (float) ($data['exchange_rate'] ?? 1.0);
            $amountUsd = (float) $data['amount_usd'];
            $amountPaidCurrency = isset($data['amount_paid_currency'])
                ? (float) $data['amount_paid_currency']
                : round($amountUsd * $exchangeRate, 2);

            $payment = $this->repository->createPayment([
                'saas_subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'plan_code' => $planCode,
                'receipt_number' => $receiptNumber,
                'amount_usd' => $amountUsd,
                'exchange_rate' => $exchangeRate,
                'amount_paid_currency' => $amountPaidCurrency,
                'currency' => (string) ($data['currency'] ?? 'USD'),
                'payment_method' => (string) ($data['payment_method'] ?? 'transfer'),
                'reference_number' => $data['reference_number'] ?? null,
                'status' => $autoApprove ? 'approved' : 'pending',
                'period_starts_at' => $currentEnd->format('Y-m-d'),
                'period_ends_at' => $newEnd->format('Y-m-d'),
                'paid_at' => !empty($data['paid_at']) ? Carbon::parse((string) $data['paid_at']) : now(),
                'reported_by' => $data['reported_by'] ?? ($autoApprove ? 'Administrador Central' : $subscription->company_name),
                'approved_by' => $autoApprove ? $userId : null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($autoApprove) {
                $this->applyRenewalToSubscription($subscription, $planModel, $planCode, $newEnd);
            }

            return $payment->load('subscription');
        });
    }

    /**
     * @inheritDoc
     */
    public function approvePayment(int $paymentId, ?int $userId = null): SaasSubscriptionPayment
    {
        return DB::transaction(function () use ($paymentId, $userId) {
            $payment = $this->repository->findPaymentById($paymentId);
            if (!$payment || !$payment->subscription) {
                throw new InvalidArgumentException('Pago o suscripción no encontrada.');
            }

            if ($payment->status === 'approved') {
                return $payment;
            }

            $subscription = $payment->subscription;
            $planCode = $payment->plan_code ?: $subscription->plan_code;
            $planModel = $this->repository->findPlanByCodeOrId($planCode);

            $newEnd = $payment->period_ends_at
                ? Carbon::parse($payment->period_ends_at)
                : Carbon::today()->addMonth();

            $payment->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'paid_at' => $payment->paid_at ?? now(),
            ]);

            $this->applyRenewalToSubscription($subscription, $planModel, $planCode, $newEnd);

            return $payment->fresh(['subscription']);
        });
    }

    /**
     * @inheritDoc
     */
    public function rejectPayment(int $paymentId, ?string $notes = null): SaasSubscriptionPayment
    {
        $payment = $this->repository->findPaymentById($paymentId);
        if (!$payment) {
            throw new InvalidArgumentException('El registro de pago no existe.');
        }

        $payment->update([
            'status' => 'rejected',
            'notes' => $notes ?: $payment->notes,
        ]);

        return $payment->fresh(['subscription']);
    }

    /**
     * @inheritDoc
     */
    public function changeSubscriptionStatus(string $tenantId, string $status): SaasSubscription
    {
        $statusEnum = SaasSubscriptionStatusEnum::tryFrom($status);
        if (!$statusEnum) {
            throw new InvalidArgumentException("Estado de suscripción inválido: {$status}");
        }

        $tenant = Tenant::find($tenantId);
        $subscription = $this->repository->findByTenantId($tenantId);

        if (!$subscription && $tenant) {
            $subscription = $this->syncTenantSubscription($tenant);
        }

        if (!$subscription) {
            throw new InvalidArgumentException("No existe suscripción para el tenant '{$tenantId}'.");
        }

        $subscription->update([
            'status' => $statusEnum->value,
            'suspended_at' => $statusEnum === SaasSubscriptionStatusEnum::SUSPENDED ? now() : null,
        ]);

        if ($tenant) {
            $tenant->is_branch_active = $statusEnum->canOperate();
            $tenant->save();
        }

        $this->flushTenantSubscriptionCache($tenantId);

        return $subscription->fresh(['plan']);
    }

    /**
     * @inheritDoc
     */
    public function regenerateLicenseKey(string $tenantId): SaasSubscription
    {
        $subscription = $this->repository->findByTenantId($tenantId);
        if (!$subscription) {
            $tenant = Tenant::findOrFail($tenantId);
            $subscription = $this->syncTenantSubscription($tenant);
        }

        $newKey = $this->generateUniqueLicenseKey($tenantId, (string) $subscription->plan_code);
        $subscription->update(['license_key' => $newKey]);

        $this->flushTenantSubscriptionCache($tenantId);

        return $subscription->fresh(['plan']);
    }

    /**
     * @inheritDoc
     */
    public function verifyLicenseKey(string $licenseKey, ?string $domain = null, ?string $tenantId = null): array
    {
        $subscription = $this->repository->findByLicenseKey($licenseKey);

        if (!$subscription) {
            return [
                'valid' => false,
                'can_operate' => false,
                'status' => 'invalid',
                'message' => 'La llave de licencia proporcionada no existe o fue revocada.',
            ];
        }

        if (!empty($tenantId) && strtolower($tenantId) !== strtolower((string) $subscription->tenant_id)) {
            return [
                'valid' => false,
                'can_operate' => false,
                'status' => 'mismatch',
                'message' => 'La llave de licencia no corresponde al identificador de empresa indicado.',
            ];
        }

        $statusEnum = SaasSubscriptionStatusEnum::tryFrom((string) $subscription->status) ?? SaasSubscriptionStatusEnum::ACTIVE;

        return [
            'valid' => true,
            'can_operate' => $statusEnum->canOperate(),
            'status' => $statusEnum->value,
            'status_label' => $statusEnum->label(),
            'tenant_id' => $subscription->tenant_id,
            'company_name' => $subscription->company_name,
            'vertical' => $subscription->vertical,
            'plan_code' => $subscription->plan_code,
            'plan_name' => $subscription->plan?->name ?? strtoupper((string) $subscription->plan_code),
            'current_period_ends_at' => $subscription->current_period_ends_at?->format('Y-m-d'),
            'grace_period_ends_at' => $subscription->grace_period_ends_at?->format('Y-m-d'),
            'max_users' => (int) ($subscription->max_users ?? 5),
            'max_branches' => (int) ($subscription->max_branches ?? 1),
            'included_modules' => $subscription->plan?->included_modules ?? [],
        ];
    }

    /**
     * @inheritDoc
     */
    public function processExpirationsAndSuspensions(): array
    {
        if (!$this->hasSaasTables()) {
            return ['moved_to_past_due' => 0, 'moved_to_suspended' => 0];
        }

        $today = Carbon::today()->format('Y-m-d');
        $movedToPastDue = 0;
        $movedToSuspended = 0;

        // 1. Pasar de active/trial a past_due cuando vence current_period_ends_at
        $expiredActives = SaasSubscription::query()
            ->whereIn('status', [SaasSubscriptionStatusEnum::ACTIVE->value, SaasSubscriptionStatusEnum::TRIAL->value])
            ->whereNotNull('current_period_ends_at')
            ->whereDate('current_period_ends_at', '<', $today)
            ->get();

        foreach ($expiredActives as $sub) {
            $graceEnd = $sub->grace_period_ends_at
                ? Carbon::parse($sub->grace_period_ends_at)->format('Y-m-d')
                : Carbon::parse($sub->current_period_ends_at)->addDays(5)->format('Y-m-d');

            $sub->update([
                'status' => SaasSubscriptionStatusEnum::PAST_DUE->value,
                'grace_period_ends_at' => $graceEnd,
            ]);

            if ($sub->tenant_id) {
                $this->flushTenantSubscriptionCache((string) $sub->tenant_id);
            }
            $movedToPastDue++;
        }

        // 2. Pasar de past_due a suspended cuando vence grace_period_ends_at
        $expiredGrace = SaasSubscription::query()
            ->where('status', SaasSubscriptionStatusEnum::PAST_DUE->value)
            ->whereNotNull('grace_period_ends_at')
            ->whereDate('grace_period_ends_at', '<', $today)
            ->get();

        foreach ($expiredGrace as $sub) {
            $sub->update([
                'status' => SaasSubscriptionStatusEnum::SUSPENDED->value,
                'suspended_at' => now(),
            ]);

            if ($sub->tenant_id) {
                $tenant = Tenant::find($sub->tenant_id);
                if ($tenant) {
                    $tenant->is_branch_active = false;
                    $tenant->save();
                }
                $this->flushTenantSubscriptionCache((string) $sub->tenant_id);
            }
            $movedToSuspended++;
        }

        return [
            'moved_to_past_due' => $movedToPastDue,
            'moved_to_suspended' => $movedToSuspended,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getPaginatedPayments(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->repository->paginatePayments($filters, $perPage);
    }

    /**
     * Aplicar la renovación sobre la suscripción y el registro del Tenant.
     */
    protected function applyRenewalToSubscription(
        SaasSubscription $subscription,
        ?SaasPlan $planModel,
        string $planCode,
        Carbon $newEnd
    ): void {
        $graceDays = (int) ($planModel?->grace_days ?? 5);
        $graceEnd = (clone $newEnd)->addDays($graceDays);

        $updateData = [
            'saas_plan_id' => $planModel?->id ?? $subscription->saas_plan_id,
            'plan_code' => $planCode,
            'status' => SaasSubscriptionStatusEnum::ACTIVE->value,
            'current_period_ends_at' => $newEnd->format('Y-m-d'),
            'grace_period_ends_at' => $graceEnd->format('Y-m-d'),
            'suspended_at' => null,
        ];

        if ($planModel) {
            $updateData['max_users'] = $planModel->max_users;
            $updateData['max_branches'] = $planModel->max_branches;
            $updateData['price_agreed_usd'] = $planModel->price_usd;
        }

        $subscription->update($updateData);

        if ($subscription->tenant_id) {
            $tenant = Tenant::find($subscription->tenant_id);
            if ($tenant) {
                $tenant->plan = $planCode;
                $tenant->plan_id = $planCode;
                $tenant->monthly_price = (float) ($updateData['price_agreed_usd'] ?? $subscription->price_agreed_usd);
                $tenant->billing_date = $newEnd->format('Y-m-d');
                $tenant->is_branch_active = true;
                if ($planModel) {
                    $tenant->max_branches = (int) $planModel->max_branches;
                    $tenant->can_create_branches = ((int) $planModel->max_branches) > 1;
                }
                $tenant->save();
            }

            $this->flushTenantSubscriptionCache((string) $subscription->tenant_id);
        }
    }

    /**
     * Generar llave de licencia única con formato comercial legible.
     */
    protected function generateUniqueLicenseKey(string $tenantId, string $planCode): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tenantId), 0, 4) ?: 'TOVA');
        $planTag = strtoupper(substr($planCode, 0, 3) ?: 'STD');
        $year = date('Y');

        do {
            $block1 = strtoupper(Str::random(4));
            $block2 = strtoupper(Str::random(4));
            $candidate = "TOVA-{$prefix}-{$planTag}{$year}-{$block1}-{$block2}";
        } while ($this->hasSaasTables() && SaasSubscription::where('license_key', $candidate)->exists());

        return $candidate;
    }

    /**
     * Invalidar caché de estado de suscripción de un tenant.
     */
    public function flushTenantSubscriptionCache(string $tenantId): void
    {
        Cache::forget("saas_subscription_state:{$tenantId}");
    }

    /**
     * Verificar si las tablas SaaS existen en la conexión central.
     */
    protected function hasSaasTables(): bool
    {
        try {
            $centralConn = (string) config('tenancy.database.central_connection', config('database.default'));
            return Schema::connection($centralConn)->hasTable('saas_subscriptions')
                && Schema::connection($centralConn)->hasTable('saas_plans');
        } catch (\Throwable) {
            return false;
        }
    }
}
