<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuppliersConfigProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'barcode',
        'product_name',
        'laboratory',
        'active_ingredient',
        'price',
        'expiration_date',
        'head_row_number',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
