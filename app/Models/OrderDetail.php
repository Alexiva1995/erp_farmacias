<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderDetail extends Model
{
    protected $fillable = [
        'order_id',
        'pack_id',
        'dish_id',
        'court_id',
        'product_type',
        'product_id',
        'quantity',
        'quantity_expiration',
        'price',
        'unit_cost',
        'unit_price_usd',
        'discount_percentage',
        'discount_type',
        'discount_source_id',
        'price_before_discount',
        'price_bs',
        'price_before_discount_bs',
        'notes',
        'kitchen_status',
        'course_number',
        'commanded_at',
        'prepared_at',
    ];
    
    protected $casts = [
        'quantity' => 'float',
        'quantity_expiration' => 'float',
        'kitchen_status' => \App\Enums\KitchenStatusEnum::class,
        'course_number' => 'integer',
        'commanded_at' => 'datetime',
        'prepared_at' => 'datetime',
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

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'court_id');
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderDetailModifier::class, 'order_detail_id');
    }
}
