<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReturn extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'supplier_discount_percentage',
        'quantity',
        'amount_refunded',
        'return_date',
        'lot_number',
        'expiration_date',
    ];

    protected $casts = [
        'return_date' => 'date',
        'quantity' => 'decimal:2',
        'amount_refunded' => 'decimal:2',
    ];

    /**
     * Relación con la factura
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
