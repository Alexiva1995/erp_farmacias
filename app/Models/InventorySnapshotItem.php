<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySnapshotItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_snapshot_items';

    protected $fillable = [
        'inventory_snapshot_id',
        'product_id',
        'product_name',
        'laboratory_name',
        'sales_class',
        'sold_units_30d',
        'total_sales_usd_30d',
        'current_stock_units',
        'unit_cost_usd',
        'sale_price_usd',
        'inventory_value_usd',
        'margin_percentage',
        'coverage_days',
        'gmroi_annual_percentage',
        'days_to_expiration',
        'risk_expiring_units',
        'risk_expiring_value_usd',
        'has_expiration_risk',
        'is_overstock',
    ];

    protected $casts = [
        'sold_units_30d' => 'float',
        'total_sales_usd_30d' => 'float',
        'current_stock_units' => 'float',
        'unit_cost_usd' => 'float',
        'sale_price_usd' => 'float',
        'inventory_value_usd' => 'float',
        'margin_percentage' => 'float',
        'coverage_days' => 'float',
        'gmroi_annual_percentage' => 'float',
        'days_to_expiration' => 'integer',
        'risk_expiring_units' => 'float',
        'risk_expiring_value_usd' => 'float',
        'has_expiration_risk' => 'boolean',
        'is_overstock' => 'boolean',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(InventorySnapshot::class, 'inventory_snapshot_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
