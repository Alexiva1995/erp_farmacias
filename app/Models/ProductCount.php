<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCount extends Model
{
    protected $fillable = [
        'cycle_id',
        'product_id',
        'product_lot_id',
        'counted_quantity',
        'system_quantity',
        'discrepancy',
        'status',
        'user_id',
        'supervisor_id',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(InventoryCycle::class, 'cycle_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
