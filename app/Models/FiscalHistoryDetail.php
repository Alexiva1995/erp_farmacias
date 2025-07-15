<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalHistoryDetail extends Model
{
    protected $fillable = [
        'fiscal_history_id',
        'product_id',
        'product_name',
        'quantity',
        'vat_status',
        'exempt_amount',
        'iva_amount',
        'total_amount',
    ];

    public function fiscalHistory(): BelongsTo
    {
        return $this->belongsTo(FiscalHistory::class);
    }
}
