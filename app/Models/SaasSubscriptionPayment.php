<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class SaasSubscriptionPayment extends Model
{
    use CentralConnection;

    protected $table = 'saas_subscription_payments';

    protected $fillable = [
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
    ];

    protected $casts = [
        'amount_usd' => 'float',
        'exchange_rate' => 'float',
        'amount_paid_currency' => 'float',
        'period_starts_at' => 'date',
        'period_ends_at' => 'date',
        'paid_at' => 'datetime',
    ];

    /**
     * Suscripción a la que pertenece el pago.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SaasSubscription::class, 'saas_subscription_id');
    }
}
