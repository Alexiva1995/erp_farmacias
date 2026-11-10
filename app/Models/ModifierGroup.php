<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'base_product_id',
        'min_selections',
        'max_selections',
        'is_active',
    ];

    protected $casts = [
        'base_product_id' => 'integer',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'is_active' => 'boolean',
    ];

    public function baseProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'base_product_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort_order', 'asc');
    }

    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'dish_modifier_group')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
