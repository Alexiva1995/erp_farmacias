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
}
