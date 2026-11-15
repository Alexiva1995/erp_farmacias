<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class SaasPlan extends Model
{
    use CentralConnection;

    protected $table = 'saas_plans';

    protected $fillable = [
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
    ];

    protected $casts = [
        'price_usd' => 'float',
        'setup_fee_usd' => 'float',
        'trial_days' => 'integer',
        'grace_days' => 'integer',
        'max_users' => 'integer',
        'max_branches' => 'integer',
        'included_modules' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Suscripciones asociadas a este plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class, 'saas_plan_id');
    }
}
