<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'active_ingredient',
        'laboratory_id',
        'supplier_id',
        'origin_id',
        'category_id',
        'group_id',
        'unit_cost',
        'sale_price',
        'iva',
        'is_colombian_origin',
        'psychotropic',
        'barcode',
        'photo_url',
        'sales_average',
        'group_id',
        'cycle_id',
        'is_deleted'
    ];

    protected $appends = ['formatted_details'];

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

    /**
     * Un producto pertenece a un laboratorio.
     */
    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->attributes['photo_url'] ? Storage::url($this->attributes['photo_url']) : null,
        );
    }

    /**
     * Un producto pertenece a un proveedor.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Un producto tiene un origen.
     */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(Origin::class);
    }

    /**
     * Un producto pertenece a una categoría.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Un producto tiene muchos lotes.
     */
    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }

    public function productSuppliers()
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function expirations()
    {
        return $this->hasMany(Expiration::class);
    }

    public function getFormattedDetailsAttribute()
    {
        return $this->active_ingredient . ($this->laboratory ? ' - ' . $this->laboratory->name : '');
    }

    public function individualOffers()
    {
        return $this->hasMany(IndividualOffer::class);
    }

    public function returns()
    {
        return $this->hasMany(ReturnEntry::class);
    }

    public function quotationLinks()
    {
        return $this->hasMany(QuotationProduct::class);
    }

    public function profitability()
    {
        return $this->hasOne(ProductProfitability::class);
    }

    public function productCounts()
    {
        return $this->hasMany(ProductCount::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function psychotropicControls()
    {
        return $this->hasMany(PsychotropicControl::class);
    }
}
