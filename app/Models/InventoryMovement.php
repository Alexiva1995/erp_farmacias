<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'product_lot_id',
        'movement_type',
        'quantity',
        'invoice_id',
        'supplier_id',
        'order_id',
        'dish_id',
        'user_id',
        'product_count_id',
        'stock_before',
        'stock_after',
        'movement_date',
    ];
    
    protected $casts = [
        'quantity' => 'float',
        'stock_before' => 'float',
        'stock_after' => 'float',
        'movement_date' => 'datetime',
    ];
    protected function movementType(): Attribute
    {
        return Attribute::make(
            get: fn($value) => match ($value) {
                'return' => 'Devolución',
                'sale' => 'Venta',
                'purchase' => 'Compra',
                'adjustment' => 'Ajuste',
                'loss' => 'Pérdida',
                'expired' => 'Caducado',
                'verification' => 'Verificado',
                default => ucfirst($value),
            },
        );
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productCount(): BelongsTo
    {
        return $this->belongsTo(ProductCount::class);
    }
}
