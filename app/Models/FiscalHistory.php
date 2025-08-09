<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalHistory extends Model
{
    protected $table = 'fiscal_history';
    protected $fillable = [
        'user_id',
        'order_id',
        'invoice_number',
        'business_name',
        'identification',
        'address',
        'exempt_amount',
        'iva_amount',
        'total_amount',
        'invoice_date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
