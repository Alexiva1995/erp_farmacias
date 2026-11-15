<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SaasSubscriptionRepositoryInterface;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\SaasSubscriptionPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SaasSubscriptionRepository implements SaasSubscriptionRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function getAllPlans(bool $onlyActive = false): Collection
    {
        $query = SaasPlan::query()
            ->select([
                'id',
                'code',
                'name',
                'description',
                'billing_cycle',
                'price_usd',
                'setup_fee_usd',
                'trial_days',
                'grace_days',
                'max_users',
                'max_branches',
                'included_modules',
                'features',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->withCount('subscriptions')
            ->orderBy('price_usd', 'asc');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * @inheritDoc
     */
    public function findPlanByCodeOrId(string|int $codeOrId): ?SaasPlan
    {
        if (is_numeric($codeOrId)) {
            $plan = SaasPlan::find((int) $codeOrId);
            if ($plan) {
                return $plan;
            }
        }

        return SaasPlan::where('code', strtolower(trim((string) $codeOrId)))->first();
    }

    /**
     * @inheritDoc
     */
    public function savePlan(array $data, ?int $id = null): SaasPlan
    {
        if ($id !== null) {
            $plan = SaasPlan::findOrFail($id);
            $plan->update($data);
            return $plan;
        }

        return SaasPlan::updateOrCreate(
            ['code' => strtolower(trim((string) $data['code']))],
            $data
        );
    }

    /**
     * @inheritDoc
     */
    public function deletePlan(int $id): bool
    {
        $plan = SaasPlan::findOrFail($id);
        return (bool) $plan->delete();
    }

    /**
     * @inheritDoc
     */
    public function findByTenantId(string $tenantId): ?SaasSubscription
    {
        return SaasSubscription::with(['plan', 'payments' => fn ($q) => $q->orderBy('paid_at', 'desc')->limit(10)])
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * @inheritDoc
     */
    public function findByLicenseKey(string $licenseKey): ?SaasSubscription
    {
        return SaasSubscription::with('plan')
            ->where('license_key', strtoupper(trim($licenseKey)))
            ->first();
    }

    /**
     * @inheritDoc
     */
    public function findById(int $id): ?SaasSubscription
    {
        return SaasSubscription::with(['plan', 'payments' => fn ($q) => $q->orderBy('paid_at', 'desc')])
            ->find($id);
    }

    /**
     * @inheritDoc
     */
    public function upsertForTenant(string $tenantId, array $attributes): SaasSubscription
    {
        return SaasSubscription::updateOrCreate(
            ['tenant_id' => $tenantId],
            $attributes
        );
    }

    /**
     * @inheritDoc
     */
    public function getAllSubscriptions(): Collection
    {
        return SaasSubscription::query()
            ->select([
                'id',
                'tenant_id',
                'company_name',
                'contact_name',
                'contact_email',
                'contact_phone',
                'domain',
                'vertical',
                'saas_plan_id',
                'plan_code',
                'billing_cycle',
                'license_key',
                'status',
                'price_agreed_usd',
                'starts_at',
                'trial_ends_at',
                'current_period_ends_at',
                'grace_period_ends_at',
                'suspended_at',
                'is_remote',
                'auto_renew',
                'max_users',
                'max_branches',
                'seller_name',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->with(['plan:id,code,name,billing_cycle,price_usd'])
            ->orderBy('current_period_ends_at', 'asc')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function createPayment(array $data): SaasSubscriptionPayment
    {
        return SaasSubscriptionPayment::create($data);
    }

    /**
     * @inheritDoc
     */
    public function paginatePayments(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = SaasSubscriptionPayment::query()
            ->select([
                'id',
                'saas_subscription_id',
                'tenant_id',
                'plan_code',
                'receipt_number',
                'amount_usd',
                'exchange_rate',
                'amount_paid_currency',
                'currency',
                'payment_method',
                'reference_number',
                'status',
                'period_starts_at',
                'period_ends_at',
                'paid_at',
                'reported_by',
                'approved_by',
                'notes',
                'created_at',
            ])
            ->with(['subscription:id,tenant_id,company_name,license_key']);

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', (string) $filters['tenant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('receipt_number', 'like', $term)
                    ->orWhere('reference_number', 'like', $term)
                    ->orWhere('tenant_id', 'like', $term)
                    ->orWhereHas('subscription', function ($sq) use ($term) {
                        $sq->where('company_name', 'like', $term);
                    });
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * @inheritDoc
     */
    public function findPaymentById(int $paymentId): ?SaasSubscriptionPayment
    {
        return SaasSubscriptionPayment::with('subscription')->find($paymentId);
    }
}
