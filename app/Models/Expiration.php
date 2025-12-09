<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expiration extends Model
{
    protected $fillable = [
        'inventory_cycle_id',
        'product_id',
        'product_lot_id',
        'supplier_id',
        'quantity',
        'unit_cost',
        'expiration_date',
        'total_cost'
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(InventoryCycle::class, 'inventory_cycle_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'product_lot_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
    
    /* Relacion con ExpirationOffer */
    public function expirationOffer(): BelongsTo
    {
        return $this->belongsTo(ExpirationOffer::class);
    }
}
