<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPack extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'pack_config',
        'total_price',
        'max_quantity',
        'max_sale_date',
        'is_active',
    ];

    protected $casts = [
        'pack_config' => 'array',
        'max_sale_date' => 'datetime',
        'total_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * @property-read array $products_with_quantity
     */
    protected $appends = ['products_with_quantity', 'is_available', 'products_count'];

    /**
     * Relación con los productos del pack
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_pack_items')
            ->withPivot('quantity', 'discount_percentage', 'sale_price')
            ->withTimestamps();
    }

    public function getProductsWithQuantityAttribute()
    {
        $products = [];

        if ($this->pack_config) {
            foreach ($this->pack_config as $productId => $config) {
                $products[$productId] = is_array($config) ?
                    ($config['quantity'] ?? $config) :
                    $config;
            }
        }

        return $products;
        //return $this->pack_config ?? [];
    }

    /**
     * Accesor para contar productos en el pack
     */
    public function getProductsCountAttribute()
    {
        return count($this->pack_config ?? []);
    }

    /**
     * Accesor para verificar disponibilidad del pack
     */
    public function getIsAvailableAttribute()
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->max_sale_date && $this->max_sale_date->isPast()) {
            return false;
        }

        if ($this->max_quantity && $this->orderDetails()->count() >= $this->max_quantity) {
            return false;
        }

        return true;
    }

    /**
     * Verificar si hay stock suficiente para todos los productos del pack
     */
    public function hasSufficientStock(): bool
    {
        if (!$this->pack_config) {
            return false;
        }

        foreach ($this->pack_config as $productId => $config) {
            $quantity = is_array($config) ? ($config['quantity'] ?? 0) : $config;
            $product = Product::find($productId);

            if (!$product || $product->stock < $quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcular el precio total del pack
     */
    public function calculateTotalPrice(): float
    {
        $total = 0;

        if ($this->pack_config) {
            foreach ($this->pack_config as $productId => $config) {
                if (is_array($config)) {
                    // Formato nuevo: con precio específico
                    $total += ($config['sale_price'] ?? 0) * ($config['quantity'] ?? 0);
                } else {
                    // Formato antiguo: usar precio del producto
                    $product = Product::find($productId);
                    if ($product) {
                        $total += $product->sale_price * $config;
                    }
                }
            }
        }

        return round($total, 2);
    }

    /**
     * Obtener información enriquecida de los productos del pack
     */
    public function getProductsInfoAttribute()
    {
        $productsInfo = [];

        if ($this->pack_config) {
            $productIds = array_keys($this->pack_config);
            $products = Product::whereIn('id', $productIds)->with('laboratory')->get()->keyBy('id');

            foreach ($this->pack_config as $productId => $config) {
                $product = $products->get($productId);

                if ($product) {
                    $quantity = is_array($config) ? ($config['quantity'] ?? 0) : $config;
                    $discountPercentage = is_array($config) ? ($config['discount_percentage'] ?? 0) : 0;
                    $salePrice = is_array($config) ? ($config['sale_price'] ?? $product->sale_price) : $product->sale_price;

                    $productsInfo[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'discount_percentage' => $discountPercentage,
                        'sale_price' => $salePrice,
                        'subtotal' => $quantity * $salePrice,
                        'product_info' => [
                            'stock' => $product->stock,
                            'unit_cost' => $product->unit_cost,
                            'next_expiration' => $product->lots->min('expiration_date'),
                            'laboratory' => $product->laboratory->name ?? null,
                            'active_ingredient' => $product->active_ingredient,
                        ]
                    ];
                }
            }
        }

        return $productsInfo;
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'pack_id');
    }

    /**
     * Scope para packs activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para packs disponibles
     */
    public function scopeAvailable($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('max_sale_date')
                    ->orWhere('max_sale_date', '>', now());
            });
    }

    /**
     * Scope para búsqueda por nombre
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%");
    }
}
