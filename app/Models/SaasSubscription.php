<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class SaasSubscription extends Model
{
    use CentralConnection;

    protected $table = 'saas_subscriptions';

    protected $fillable = [
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
    ];

    protected $casts = [
        'price_agreed_usd' => 'float',
        'starts_at' => 'date',
        'trial_ends_at' => 'date',
        'current_period_ends_at' => 'date',
        'grace_period_ends_at' => 'date',
        'suspended_at' => 'datetime',
        'is_remote' => 'boolean',
        'auto_renew' => 'boolean',
        'max_users' => 'integer',
        'max_branches' => 'integer',
    ];

    /**
     * Plan contratado.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    /**
     * Historial de pagos y renovaciones de la suscripción.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SaasSubscriptionPayment::class, 'saas_subscription_id');
    }
}
