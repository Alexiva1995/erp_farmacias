<?php

namespace App\Models;

use App\Models\Concerns\HasPromotionSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPack extends Model
{
    use HasPromotionSchedule, SoftDeletes;

    protected $fillable = [
        'name',
        'pack_config',
        'total_price',
        'max_quantity',
        'max_sale_date',
        'days_of_week',
        'start_time',
        'end_time',
        'sales_channel',
        'is_active',
    ];

    protected $casts = [
        'pack_config' => 'array',
        'max_sale_date' => 'datetime',
        'days_of_week' => 'array',
        'total_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * @property-read array $products_with_quantity
     */
    protected $appends = ['products_with_quantity', 'is_available', 'products_count', 'sales_count'];

    /**
     * Ciclo de vida del modelo Eloquent
     */
    protected static function booted(): void
    {
        static::saved(function (ProductPack $productPack) {
            if ($productPack->pack_config && is_array($productPack->pack_config)) {
                $syncData = [];
                $dishRows = [];
                foreach ($productPack->pack_config as $key => $config) {
                    $isDish = (is_string($key) && str_starts_with($key, 'dish_'))
                        || (is_array($config) && (($config['item_type'] ?? null) === 'dish' || !empty($config['dish_id'])));

                    $quantity = is_array($config) ? (int) ($config['quantity'] ?? 1) : (int) $config;
                    $discountPercentage = is_array($config) ? (float) ($config['discount_percentage'] ?? 0.00) : 0.00;
                    $salePrice = is_array($config) ? (float) ($config['sale_price'] ?? 0.00) : 0.00;

                    if ($isDish) {
                        $dishId = is_array($config) && !empty($config['dish_id'])
                            ? (int) $config['dish_id']
                            : (int) str_replace('dish_', '', (string) $key);
                        if ($salePrice <= 0 && class_exists(Dish::class)) {
                            $dish = Dish::find($dishId);
                            $salePrice = $dish ? (float) $dish->designated_price : 0.00;
                        }
                        $dishRows[] = [
                            'pack_id' => $productPack->id,
                            'product_id' => null,
                            'dish_id' => $dishId,
                            'quantity' => $quantity,
                            'discount_percentage' => $discountPercentage,
                            'sale_price' => $salePrice,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    } else {
                        $productId = (int) $key;
                        if (!is_array($config)) {
                            $product = Product::find($productId);
                            if ($product) {
                                $salePrice = (float) $product->sale_price;
                            }
                        }
                        $syncData[$productId] = [
                            'quantity' => $quantity,
                            'discount_percentage' => $discountPercentage,
                            'sale_price' => $salePrice,
                        ];
                    }
                }
                $productPack->products()->sync($syncData);
                \Illuminate\Support\Facades\DB::table('product_pack_items')
                    ->where('pack_id', $productPack->id)
                    ->whereNotNull('dish_id')
                    ->delete();
                if (!empty($dishRows)) {
                    \Illuminate\Support\Facades\DB::table('product_pack_items')->insert($dishRows);
                }
            } else {
                $productPack->products()->detach();
                \Illuminate\Support\Facades\DB::table('product_pack_items')
                    ->where('pack_id', $productPack->id)
                    ->delete();
            }
        });
    }

    /**
     * Relación con los productos del pack
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_pack_items', 'pack_id', 'product_id')
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
     * Accesor para obtener la cantidad de veces que se ha vendido el pack
     */
    public function getSalesCountAttribute()
    {
        return \Illuminate\Support\Facades\DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('order_details.pack_id', $this->id)
            ->where('orders.status', 'Completed')
            ->distinct('order_details.order_id')
            ->count();
    }

    /**
     * Accesor para verificar disponibilidad del pack
     */
    public function getIsAvailableAttribute()
    {
        if (!$this->isCurrentlyValid()) {
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

        foreach ($this->pack_config as $key => $config) {
            $isDish = (is_string($key) && str_starts_with($key, 'dish_'))
                || (is_array($config) && (($config['item_type'] ?? null) === 'dish' || !empty($config['dish_id'])));

            if ($isDish) {
                $dishId = is_array($config) && !empty($config['dish_id'])
                    ? (int) $config['dish_id']
                    : (int) str_replace('dish_', '', (string) $key);
                $dish = Dish::find($dishId);
                if (!$dish || $dish->status === 'inactive') {
                    return false;
                }
                continue;
            }

            $quantity = is_array($config) ? ($config['quantity'] ?? 0) : $config;
            $product = Product::find($key);

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
                        'sale_price_original' => (float) $product->sale_price,
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
