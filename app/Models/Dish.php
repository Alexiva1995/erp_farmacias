<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Dish extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cost_price',
        'cpv',
        'suggested_price',
        'designated_price',
        'percentage_profit',
        'category_id',
        'status',
        'photo_url',
        'description',
        'iva',
        'tax_rate',
        'preparation_time_minutes',
        'kitchen_station_id',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'cpv' => 'decimal:2',
        'suggested_price' => 'decimal:2',
        'designated_price' => 'decimal:2',
        'percentage_profit' => 'decimal:2',
        'iva' => 'boolean',
        'tax_rate' => 'decimal:2',
        'preparation_time_minutes' => 'integer',
        'kitchen_station_id' => 'integer',
    ];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dish_ingredients', 'dish_id', 'product_id')
            ->withPivot('id', 'portion', 'designated_cost')
            ->withTimestamps();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'dish_modifier_group')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
