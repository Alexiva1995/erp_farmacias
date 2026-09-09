<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySnapshot extends Model
{
    use HasFactory;

    protected $table = 'inventory_snapshots';

    protected $fillable = [
        'name',
        'cutoff_date',
        'period_days',
        'total_products',
        'total_inventory_units',
        'total_inventory_value',
        'total_sales_units',
        'total_sales_value',
        'overstock_products_count',
        'overstock_inventory_value',
        'is_automatic',
        'created_by_user_id',
    ];

    protected $casts = [
        'cutoff_date' => 'date',
        'period_days' => 'integer',
        'total_products' => 'integer',
        'total_inventory_units' => 'float',
        'total_inventory_value' => 'float',
        'total_sales_units' => 'float',
        'total_sales_value' => 'float',
        'overstock_products_count' => 'integer',
        'overstock_inventory_value' => 'float',
        'is_automatic' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InventorySnapshotItem::class, 'inventory_snapshot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
