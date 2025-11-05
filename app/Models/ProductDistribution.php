<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDistribution extends Model
{
    protected $fillable = [
        'product_count_id',
        'product_lot_id',
        'quantity',
    ];

    public function productCount(): BelongsTo
    {
        return $this->belongsTo(ProductCount::class);
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }
}
