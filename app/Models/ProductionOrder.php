<?php

namespace App\Models;

use App\Enums\ProductionOrderStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $table = 'production_orders';

    protected $fillable = [
        'code',
        'dish_id',
        'output_product_id',
        'user_id',
        'kitchen_station_id',
        'batch_multiplier',
        'expected_quantity',
        'actual_quantity',
        'total_production_cost',
        'unit_cost_calculated',
        'generated_lot_id',
        'status',
        'notes',
        'produced_at',
    ];

    protected $casts = [
        'batch_multiplier' => 'decimal:2',
        'expected_quantity' => 'decimal:2',
        'actual_quantity' => 'decimal:2',
        'total_production_cost' => 'decimal:2',
        'unit_cost_calculated' => 'decimal:2',
        'status' => ProductionOrderStatusEnum::class,
        'produced_at' => 'datetime',
    ];

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }

    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }

    public function generatedLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'generated_lot_id');
    }
}
