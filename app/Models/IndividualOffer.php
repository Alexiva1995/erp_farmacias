<?php

namespace App\Models;

use App\Models\Concerns\HasPromotionSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndividualOffer extends Model
{
    use HasPromotionSchedule;

    protected $fillable = [
        'product_id',
        'dish_id',
        'discount_percent',
        'start_date',
        'end_date',
        'days_of_week',
        'start_time',
        'end_time',
        'one_per_customer',
        'is_active',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'days_of_week' => 'array',
        'one_per_customer' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
