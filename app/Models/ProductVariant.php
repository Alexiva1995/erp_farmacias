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
        'photo_url',
        'price_modifier',
        'stock',
    ];

    protected $casts = [
        'price_modifier' => 'decimal:2',
        'stock' => 'integer',
    ];

    protected $appends = ['photo_url', 'image_url'];

    /**
     * Accesor para la URL de la foto de la variante.
     */
    protected function photoUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function () {
                $url = $this->attributes['photo_url'] ?? null;
                if (!$url) {
                    return null;
                }
                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    return $url;
                }
                if (str_starts_with($url, '/storage/')) {
                    $url = substr($url, 9);
                } elseif (str_starts_with($url, 'storage/')) {
                    $url = substr($url, 8);
                }

                if (function_exists('tenancy') && tenancy()->initialized && function_exists('tenant_asset')) {
                    return tenant_asset($url);
                }

                return \Illuminate\Support\Facades\Storage::url($url);
            }
        );
    }

    /**
     * Accesor alias para compatibilidad con la vista de ecommerce.
     */
    protected function imageUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => $this->photo_url
        );
    }

    /**
     * Obtener el producto al que pertenece la variante.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
