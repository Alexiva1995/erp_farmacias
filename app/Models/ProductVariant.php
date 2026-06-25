<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'attribute_type',
        'attribute_value',
        'color_hex',
        'price_modifier',
        'stock',
    ];

    protected $casts = [
        'price_modifier' => 'decimal:2',
        'stock' => 'integer',
    ];

    /**
     * Obtener el producto al que pertenece la variante.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
