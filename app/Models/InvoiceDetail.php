<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDetail extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'auto_order_id',
        'lot_number',
        'expiration_date',
        'quantity',
        'unit_cost',
        'total_cost',
        'location',
        'tax_enabled'
    ];

    public const FILLABLEDETAILS = [
        'quantity',
        'unit_cost',
        'lot_number',
        'expiration_date',
        'total_cost',
        'product_id',
    ];

    protected $fillableDetails = self::FILLABLEDETAILS;

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function autoOrder(): BelongsTo
    {
        return $this->belongsTo(AutoOrder::class);
    }
}
