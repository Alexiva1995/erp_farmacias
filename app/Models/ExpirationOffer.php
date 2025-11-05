<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ExpirationOffer extends Model
{
    protected $fillable = [
        'months_to_expiration',
        'discount_percentage',
        'is_active',
    ];

    /**
     * Relación muchos a muchos con ProductLot a través de la tabla pivote
     */
    public function productLots(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductLot::class,
            'expiration_offer_product_lot',
            'expiration_offer_id',
            'product_lot_id'
        )->withTimestamps();
    }

    /**
     * Relación con expiraciones
     */
    public function expirations(): HasMany
    {
        return $this->hasMany(Expiration::class, 'expiration_offer_id');
    }

    /**
     * Accesor para contar productos asociados
     */
    public function getProductsCountAttribute(): int
    {
        return $this->productLots()->count();
    }

    /**
     * Scope para ofertas activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para ofertas por meses específicos
     */
    public function scopeByMonths($query, $months)
    {
        return $query->where('months_to_expiration', $months);
    }
    
}
