<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRating extends Model
{
    protected $fillable = [
        'supplier_id',
        'rating_date',
        'product_arrival',
        'delivery_time',
        'returns',
        'amount_ratio',
        'unit_ratio',
        'overall_rating',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
