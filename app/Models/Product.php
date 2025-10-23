<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes; // Se mantiene si se usa en el futuro, aunque no se usa directamente en este snippet
use Illuminate\Support\Facades\Storage;
use App\Services\Resources\ResourceService; // Importado de 4.0-TPV
use Illuminate\Support\Str; // Importado de develop

class Product extends Model
{
    use HasFactory;
    // Si se necesita SoftDeletes en el futuro, se añadiría aquí:
    // use SoftDeletes;

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
        'is_deleted',
        'stock',
    ];

    // Atributos que se añadirán al array del modelo cuando se serialice a JSON.
    // protected $appends = ['formatted_details', 'price_bs', 'price_cop', 'preferencia_producto'];
    protected $appends = ['formatted_details', 'price_bs', 'price_cop'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_colombian_origin' => 'boolean',
        'psychotropic' => 'boolean',
        'is_deleted' => 'boolean',
        'sale_price' => 'float',
        // Puedes añadir más casts aquí si es necesario, por ejemplo:
        // 'unit_cost' => 'decimal:2',
    ];


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

    /**
     * Accesor para la URL de la foto.
     * Si 'photo_url' existe, devuelve la URL accesible públicamente, de lo contrario, null.
     */
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
     * Un producto pertenece a un grupo de productos.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(GroupsProduct::class);
    }

    /**
     * Un producto tiene muchos lotes.
     */
    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }

    /**
     * Un producto puede tener múltiples asociaciones con proveedores (ej. precios específicos por proveedor).
     */
    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    /**
     * Un producto tiene muchas fechas de expiración asociadas.
     */
    public function expirations(): HasMany
    {
        return $this->hasMany(Expiration::class);
    }

    /**
     * Un producto puede tener múltiples ofertas individuales.
     */
    public function individualOffers(): HasMany
    {
        return $this->hasMany(IndividualOffer::class);
    }

    /**
     * Un producto puede estar asociado a múltiples entradas de devoluciones.
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnEntry::class);
    }

    /**
     * Un producto puede estar en múltiples enlaces de cotización.
     */
    public function quotationLinks(): HasMany
    {
        return $this->hasMany(QuotationProduct::class);
    }

    /**
     * Un producto tiene una rentabilidad asociada.
     */
    public function profitability(): \Illuminate\Database\Eloquent\Relations\HasOne // Corregido a HasOne si es una sola rentabilidad
    {
        return $this->hasOne(ProductProfitability::class);
    }

    /**
     * Un producto puede tener múltiples conteos de inventario.
     */
    public function productCounts(): HasMany
    {
        return $this->hasMany(ProductCount::class);
    }

    /**
     * Un producto puede estar en los detalles de múltiples órdenes.
     */
    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * Un producto puede tener múltiples movimientos de inventario.
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Un producto puede estar en los detalles de múltiples facturas.
     */
    public function invoiceDetails(): HasMany
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    /**
     * Un producto puede tener múltiples controles psicotrópicos.
     */
    public function psychotropicControls(): HasMany
    {
        return $this->hasMany(PsychotropicControl::class);
    }

    /**
     * =================================================================================================
     * ACCESORES Y MUTADORES
     * =================================================================================================
     */

    /**
     * Accesor para obtener los detalles formateados del producto.
     * Combina el ingrediente activo y el nombre del laboratorio.
     */
    public function getFormattedDetailsAttribute(): string
    {
        return $this->active_ingredient . ($this->laboratory ? ' - ' . $this->laboratory->name : '');
    }

    /**
     * Método auxiliar para obtener la tasa de cambio de un servicio.
     * Utiliza el servicio `ResourceService` para obtener la tasa de cambio.
     */
    protected function getServiceExchangeRate(string $currencyCode): float
    {
        $resourceService = app(ResourceService::class);
        return $resourceService->getExchangeRate($currencyCode);
    }

    /**
     * Accesor para el precio en Bolívares (BS).
     * Calcula el precio de venta multiplicando por la tasa de cambio de BS.
     */
    protected function priceBs(): Attribute
    {
        return Attribute::make(
            get: fn() => round($this->sale_price * $this->getServiceExchangeRate('BS'), 2),
        );
    }

    /**
     * Accesor para el precio en Pesos Colombianos (COP).
     * Calcula el precio de venta multiplicando por la tasa de cambio de COP.
     */
    protected function priceCop(): Attribute
    {
        return Attribute::make(
            get: fn() => ceil($this->sale_price * $this->getServiceExchangeRate('COP') / 100) * 100,
        );
    }

    /**
     * Mutador para el atributo 'name'.
     * Convierte el nombre a mayúsculas antes de guardarlo en la base de datos.
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = Str::upper($value);
    }
    public function invoiceCounts()
    {
        return $this->hasMany(InvoiceCount::class);
    }

    /**
     * Obtener la fecha de expiración más próxima del producto
     */
    public function getNextExpirationAttribute()
    {
        $nextLot = $this->lots()
            ->withStock()
            ->where('expiration_date', '>', now())
            ->orderBy('expiration_date')
            ->first();

        return $nextLot ? $nextLot->expiration_date : null;
    }

    /**
     * Obtener el lote con la fecha de expiración más próxima
     */
    public function getNextExpiringLotAttribute()
    {
        return $this->lots()
            ->withStock()
            ->where('expiration_date', '>', now())
            ->orderBy('expiration_date')
            ->first();
    }

    /**
     * Obtener todos los lotes con stock disponibles, ordenados por expiración
     */
    public function getAvailableLotsAttribute()
    {
        return $this->lots()
            ->withStock()
            ->where('expiration_date', '>', now())
            ->orderBy('expiration_date')
            ->get();
    }

    /**
     * Verificar si el producto tiene stock disponible
     */
    public function getHasStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Obtener el stock total disponible
     */
    public function getAvailableStockAttribute(): int
    {
        return $this->lots()
            ->withStock()
            ->where('expiration_date', '>', now())
            ->sum('quantity');
    }
}
