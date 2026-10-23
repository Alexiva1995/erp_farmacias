<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModifierTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'modifier_group_id',
        'name',
        'modifier_type',
        'extra_price_usd',
        'product_id',
        'portion',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'modifier_type' => ModifierTypeEnum::class,
        'extra_price_usd' => 'decimal:4',
        'portion' => 'decimal:4',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
