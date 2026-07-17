<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductProfitability extends Model
{
    protected $table = 'product_profitability';

    protected $fillable = [
        'product_id',
        'profitability_percentage',
        'is_locked',
        'shipping_cost',
        'packaging_cost',
        'expense_margin',
        'profit_margin',
        'tax_usa',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
