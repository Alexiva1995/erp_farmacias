<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModifierTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetailModifier extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_detail_id',
        'modifier_option_id',
        'name',
        'modifier_type',
        'extra_price_usd',
        'product_id',
        'portion',
    ];

    protected $casts = [
        'extra_price_usd' => 'decimal:4',
        'portion' => 'decimal:4',
    ];

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_detail_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_option_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
