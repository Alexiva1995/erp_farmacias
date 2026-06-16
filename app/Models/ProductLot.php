<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductLot extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'product_lots';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'supplier_id',
        'lot_number',
        'expiration_date',
        'quantity',
        'location',
        'unit_cost',
        'amount_usd'
    ];
    protected $casts = [
        'expiration_date' => 'datetime',
        'quantity' => 'float',
    ];
    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */

    /**
     * =================================================================================================
     * RELACIONES
     * =================================================================================================
     */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function expirations()
    {
        return $this->hasMany(Expiration::class, 'product_lot_id');
    }

    public function productCounts()
    {
        return $this->hasMany(ProductCount::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Nueva relación: Muchos a muchos con ExpirationOffer
     */
    public function expirationOffers(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpirationOffer::class,
            'expiration_offer_product_lot',
            'product_lot_id',
            'expiration_offer_id'
        )->withTimestamps();
    }

    /**
     * Accesor para verificar si el lote está próximo a expirar
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->expiration_date) {
            return false;
        }

        return $this->months_to_expiration <= 6; // Considerar próximo a expirar si tiene 6 meses o menos
    }

    /**
     * Accesor para obtener meses hasta la expiración
     */
    public function getMonthsToExpirationAttribute(): int
    {
        if (!$this->expiration_date) {
            return 999; // Valor alto para lotes sin fecha de expiración
        }

        $now = now();
        $diffMonths = ($this->expiration_date->year - $now->year) * 12 + $this->expiration_date->month - $now->month + 1;
        return max(1, $diffMonths);
    }

    /**
     * Scope para lotes que expiran en X meses
     */
    public function scopeExpiringInMonths($query, $months)
    {
        return $query->whereRaw("((YEAR(expiration_date) - YEAR(NOW())) * 12 + MONTH(expiration_date) - MONTH(NOW()) + 1) <= ?", [$months])
                    ->whereRaw("((YEAR(expiration_date) - YEAR(NOW())) * 12 + MONTH(expiration_date) - MONTH(NOW()) + 1) >= 1");
    }

    /**
     * Scope para lotes con stock disponible
     */
    public function scopeWithStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Scope para lotes sin ofertas activas
     */
    public function scopeWithoutActiveOffers($query)
    {
        return $query->whereDoesntHave('expirationOffers', function ($q) {
            $q->where('is_active', true);
        });
    }

}
