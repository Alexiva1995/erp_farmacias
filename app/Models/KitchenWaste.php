<?php

namespace App\Models;

use App\Enums\KitchenWasteReasonEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenWaste extends Model
{
    use HasFactory;

    protected $table = 'kitchen_wastes';

    protected $fillable = [
        'user_id',
        'kitchen_station_id',
        'item_type',
        'product_id',
        'dish_id',
        'lot_id',
        'quantity',
        'unit_cost',
        'total_loss_usd',
        'reason',
        'notes',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_loss_usd' => 'decimal:2',
        'reason' => KitchenWasteReasonEnum::class,
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }
}
