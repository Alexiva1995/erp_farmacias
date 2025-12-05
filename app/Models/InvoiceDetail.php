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
        'tax_enabled',
        'auto_order_details_id'
    ];

    public const FILLABLEDETAILS = [
        'quantity',
        'unit_cost',
        'lot_number',
        'expiration_date',
        'total_cost',
        'product_id',
        'tax_enabled',
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

    public function autoOrderDetail(): BelongsTo
    {
        return $this->belongsTo(AutoOrderDetail::class);
    }
}
