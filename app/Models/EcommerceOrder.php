<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceOrder extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_orders';

    protected $fillable = [
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_document_type',
        'customer_document_number',
        'shipping_address',
        'shipping_method',
        'shipping_cost',
        'total_amount',
        'currency',
        'total_in_currency',
        'status',
        'payment_method',
        'tpv_order_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_in_currency' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tpv_order_id' => 'integer',
    ];

    /**
     * Obtener los ítems asociados a la orden de e-commerce.
     */
    public function items(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class, 'ecommerce_order_id');
    }

    /**
     * Orden del TPV vinculada si existe.
     */
    public function tpvOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'tpv_order_id');
    }
}
