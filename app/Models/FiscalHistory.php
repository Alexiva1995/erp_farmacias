<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalHistory extends Model
{
    protected $table = 'fiscal_history';
    protected $fillable = [
        'user_id',
        'fiscal_id',
        'invoice_number',
        'business_name',
        'identification',
        'address',
        'exempt_amount',
        'iva_amount',
        'total_amount',
        'invoice_date',
        'order_id',
        'spe',
        'taxable_amount',
        'spe_surcharge_rate',
        'spe_surcharge_amount',
        'exchange_rate',
        'is_queued',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // public function order(): BelongsTo
    // {
    //     return $this->belongsTo(Order::class);
    // }

    public function details(): HasMany
    {
        return $this->hasMany(FiscalHistoryDetail::class);
    }
}
