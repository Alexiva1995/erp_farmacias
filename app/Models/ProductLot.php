<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
