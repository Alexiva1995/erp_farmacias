<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\Services\Resources\ResourceService;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * El "booted" método del modelo.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('not_deleted', function ($builder) {
            $builder->where('is_deleted', false);
        });
    }

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
        'id',
        'name',
        'description',
        'active_ingredient',
        'laboratory_id',
        'supplier_id',
        'origin_id',
        'category_id',
        'group_id',
        'unit_cost',
        'lotification_completed',
        'sale_price',
        'iva',
        'is_colombian_origin',
        'psychotropic',
        'barcode',
        'photo_url',
        'sales_average',
        'cycle_id',
        'is_ordered',
        'is_scarce',
        'is_deleted',
        'is_active',
        'no_pvp',
        'stock',
        'ignore_until',
        'manual_solicitar',
        'is_unified_group',
        'is_novaventa',
        'presentation',
        'unit_of_measure',
        'is_favorite',
        'price_lock_baseline',
    ];

    /**
     * Los valores por defecto de los atributos del modelo.
     *
     * @var array
     */
    protected $attributes = [
        'stock' => 0,
    ];

    protected $appends = ['formatted_details', 'price_bs', 'price_cop', 'sale_price_cop', 'unit_cost_cop', 'discount_percentage', 'discount_type', 'discount_source_id'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_colombian_origin' => 'boolean',
        'is_novaventa' => 'boolean',
        'psychotropic' => 'boolean',
        'is_deleted' => 'boolean',
        'sale_price' => 'float',
        'is_ordered' => 'boolean',
        'is_scarce' => 'boolean',
        'ignore_until' => 'datetime',
        'is_unified_group' => 'boolean',
        'no_pvp' => 'boolean',
        'stock' => 'float',
        'is_favorite' => 'boolean',
        'price_lock_baseline' => 'float',
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
     * Un producto puede tener muchas variantes de e-commerce.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Accesor para la URL de la foto.
     * Si 'photo_url' existe, devuelve la URL accesible públicamente, de lo contrario, null.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                $url = $this->attributes['photo_url'] ?? null;
                if (!$url) return null;
                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    return $url;
                }
                // Limpiar prefijo de storage si ya existe para evitar duplicación
                if (str_starts_with($url, '/storage/')) {
                    $url = substr($url, 9);
                } elseif (str_starts_with($url, 'storage/')) {
                    $url = substr($url, 8);
                }
                return \Illuminate\Support\Facades\Storage::url($url);
            }
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
     * Lotes con stock positivo que vencen dentro de los próximos N días.
     * Umbral por defecto: 120 días (4 meses).
     * Se usa para la regla de Bloqueo de Compras.
     */
    public function expiringLots(int $days = 120): HasMany
    {
        return $this->hasMany(ProductLot::class)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addDays($days));
    }

    /**
     * Verifica si el producto tiene lotes próximos a vencer dentro de N días.
     * Retorna true → producto BLOQUEADO para nuevas compras.
     */
    public function hasExpiringLots(int $days = 120): bool
    {
        return $this->hasMany(ProductLot::class)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addDays($days))
            ->exists();
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
    public function profitability(): \Illuminate\Database\Eloquent\Relations\HasOne
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

    public function invoiceCounts(): HasMany
    {
        return $this->hasMany(InvoiceCount::class);
    }


    public function saleCounts()
    {
        return $this->hasMany(SaleCount::class);
    }

    /**
     * Un producto puede estar asignado a muchos empleados.
     * Un empleado puede tener asignados muchos productos.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_product')
            ->withTimestamps();
    }

    /**
     * =================================================================================================
     * ACCESORES Y MUTADORES
     * =================================================================================================
     */

    /**
     * Helper param obtener el mejor descuento disponible.
     */
    public function getBestProductDiscount(): ?array
    {
        $now = now();

        // 1. Individual Offer
        $individualOffer = $this->individualOffers()
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->orderByDesc('discount_percent')
            ->first();

        $indPercent = $individualOffer ? (float) $individualOffer->discount_percent : 0;

        // 2. Expiration Offer (Dynamic)
        $expPercent = 0;
        $expirationOffer = null;
        $nextLot = $this->next_expiring_lot;

        if ($nextLot) {
            $monthsToExpiration = $nextLot->months_to_expiration;

            // Find active offers that cover this expiration time (Offer Months >= Lot Months)
            $expirationOffer = \App\Models\ExpirationOffer::where('is_active', true)
                ->where('months_to_expiration', '>=', $monthsToExpiration)
                ->orderByDesc('discount_percentage')
                ->first();

            $expPercent = $expirationOffer ? (float) $expirationOffer->discount_percentage : 0;
        }

        // 3. Category Offer
        $catPercent = 0;
        $categoryOffer = null;

        if ($this->category) {
            $categoryOffer = $this->category->offers()
                ->where('is_active', true)
                ->where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->orderByDesc('discount_percentage')
                ->first();
            $catPercent = $categoryOffer ? (float) $categoryOffer->discount_percentage : 0;
        }

        // Compare logic: Return the highest discount
        $maxPercent = max($indPercent, $expPercent, $catPercent);

        if ($maxPercent <= 0) {
            return null;
        }

        if ($maxPercent === $indPercent) {
            return [
                'percentage' => $indPercent,
                'type' => 'Individual',
                'source_id' => $individualOffer->id
            ];
        } elseif ($maxPercent === $expPercent) {
            return [
                'percentage' => $expPercent,
                'type' => 'Expiration',
                'source_id' => $expirationOffer->id
            ];
        } else {
            return [
                'percentage' => $catPercent,
                'type' => 'Category',
                'source_id' => $categoryOffer->id
            ];
        }
    }

    public function getDiscountPercentageAttribute()
    {
        $best = $this->getBestProductDiscount();
        return $best ? $best['percentage'] : 0;
    }

    public function getDiscountTypeAttribute()
    {
        $best = $this->getBestProductDiscount();
        return $best ? $best['type'] : null;
    }

    public function getDiscountSourceIdAttribute()
    {
        $best = $this->getBestProductDiscount();
        return $best ? $best['source_id'] : null;
    }

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

    protected function salePriceCop(): Attribute
    {
        return Attribute::make(
            get: fn() => ceil($this->sale_price * $this->getServiceExchangeRate('COP') / 100) * 100,
        );
    }

    protected function unitCostCop(): Attribute
    {
        return Attribute::make(
            get: fn() => ceil($this->unit_cost * $this->getServiceExchangeRate('COP') / 100) * 100,
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

    /**
     * Obtener la fecha de expiración más próxima del producto
     */
    public function getNextExpirationAttribute()
    {
        $nextLot = $this->lots()
            ->withStock()
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
    /**
     * Obtener las ubicaciones únicas de los lotes del producto.
     */
    public function getLotLocationsAttribute(): array
    {
        $locations = [];
        if ($this->relationLoaded('lots')) {
            $locations = $this->lots
                ->pluck('location')
                ->filter(fn($loc) => !empty(trim((string)$loc)))
                ->unique()
                ->values()
                ->toArray();
        } else {
            $locations = $this->lots()
                ->whereNotNull('location')
                ->where('location', '!=', '')
                ->pluck('location')
                ->unique()
                ->values()
                ->toArray();
        }

        return $locations;
    }
}
