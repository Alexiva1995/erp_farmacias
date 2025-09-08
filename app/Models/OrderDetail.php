<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetail extends Model
{
    protected $fillable = [
        'order_id',
        'pack_id',
        'product_type',
        'product_id',
        'quantity',
        'price',
        'unit_cost',
        'unit_price_usd',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(ProductPack::class, 'pack_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
