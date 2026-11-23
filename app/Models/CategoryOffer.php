<?php

namespace App\Models;

use App\Models\Concerns\HasPromotionSchedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryOffer extends Model
{
    use HasFactory, HasPromotionSchedule;

    protected $fillable = [
        'category_id',
        'discount_percentage',
        'start_date',
        'end_date',
        'days_of_week',
        'start_time',
        'end_time',
        'sales_channel',
        'is_active',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'days_of_week' => 'array',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
