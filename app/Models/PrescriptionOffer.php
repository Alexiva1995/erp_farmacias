<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class PrescriptionOffer extends Model
{
    protected $fillable = [
        'discount_percentage',
        'start_date',
        'end_date',
        'is_active',
        'products',
        'total_cost',
        'name'
    ];

    public $timestamps = true;


    protected $casts = [
        'products' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'discount_percentage' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    /**
     * Boot para calcular automáticamente el total_cost
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->calculateTotalCostWithDiscount();
        });
    }

    /**
     * Calcular el costo total con descuento aplicado
     */
    public function calculateTotalCostWithDiscount(): void
    {
        $total = 0;

        if (!empty($this->products)) {
            foreach ($this->products as $productData) {
                $subtotal = $productData['sale_price'] * $productData['quantity'];
                $discountedSubtotal = $subtotal * (1 - ($this->discount_percentage / 100));
                $total += $discountedSubtotal;
            }
        }

        $this->total_cost = round($total, 2);
    }

    /**
     * Funcion para obtener los productos con información completa
     */
    public function getProductsWithDetailsAttribute()
    {
        if (empty($this->products)) {
            return [];
        }

        $productsWithDetails = [];
        foreach ($this->products as $productData) {
            $product = Product::find($productData['product_id']);
            if ($product) {
                $subtotal = $productData['sale_price'] * $productData['quantity'];
                $discountAmount = $subtotal * ($this->discount_percentage / 100);
                $finalPrice = $subtotal - $discountAmount;

                $productsWithDetails[] = [
                    'product_id' => $productData['product_id'],
                    'sale_price' => $productData['sale_price'],
                    'quantity' => $productData['quantity'],
                    'subtotal' => $subtotal,
                    'product_name' => $product->name,
                    'active_ingredient' => $product->active_ingredient,
                    'laboratory' => $product->laboratory->name ?? null,
                    'barcode' => $product->barcode,
                    'final_price' => $finalPrice,
                    'discount_amount' => $discountAmount,
                    'discount_percentage' => $this->discount_percentage,
                ];
            }
        }

        return $productsWithDetails;
    }

    /**
     * Funcion para verificar si la oferta está activa
     */
    public function getIsCurrentlyActiveAttribute(): bool
    {
        $now = now();
        return $this->is_active &&
            (!$this->start_date || $this->start_date <= $now) &&
            (!$this->end_date || $this->end_date >= $now);
    }

    /**
     * Calcular el subtotal sin descuento
     */
    public function getSubtotalWithoutDiscountAttribute(): float
    {
        if (empty($this->products)) {
            return 0;
        }

        $subtotal = 0;
        foreach ($this->products as $productData) {
            $subtotal += $productData['sale_price'] * $productData['quantity'];
        }

        return round($subtotal, 2);
    }

    /**
     * Funcion para calcular el monto total del descuento
     */
    public function getTotalDiscountAmountAttribute(): float
    {
        return round($this->subtotal_without_discount * ($this->discount_percentage / 100), 2);
    }

    /**
     * Funcion para agregar un producto a la oferta
     */
    public function addProduct(int $productId, float $salePrice, int $quantity = 1): void
    {
        $products = $this->products ?? [];

        // Verificar si el producto ya existe
        $existingIndex = collect($products)->search(function ($item) use ($productId) {
            return $item['product_id'] === $productId;
        });

        if ($existingIndex !== false) {
            // Actualizar producto existente
            $products[$existingIndex] = [
                'product_id' => $productId,
                'sale_price' => $salePrice,
                'quantity' => $quantity,
            ];
        } else {
            // Agregar nuevo producto
            $products[] = [
                'product_id' => $productId,
                'sale_price' => $salePrice,
                'quantity' => $quantity,
            ];
        }

        $this->products = $products;
        $this->calculateTotalCostWithDiscount();
    }

    /**
     * Actualizar la cantidad de un producto existente
     */
    public function updateProductQuantity(int $productId, int $quantity): bool
    {
        $products = $this->products ?? [];

        $existingIndex = collect($products)->search(function ($item) use ($productId) {
            return $item['product_id'] === $productId;
        });

        if ($existingIndex !== false) {
            $products[$existingIndex]['quantity'] = $quantity;
            $this->products = $products;
            $this->calculateTotalCostWithDiscount();
            return true;
        }

        return false;
    }

    /**
     * Funcion para remover un producto de la oferta
     */
    public function removeProduct(int $productId): bool
    {
        $products = $this->products ?? [];

        $filteredProducts = array_filter($products, function ($item) use ($productId) {
            return $item['product_id'] !== $productId;
        });

        if (count($filteredProducts) !== count($products)) {
            $this->products = array_values($filteredProducts);
            $this->calculateTotalCostWithDiscount();
            return true;
        }

        return false;
    }

    /**
     * Funcion para obtener la cantidad total de productos en la oferta
     */
    public function getTotalProductsQuantityAttribute(): int
    {
        if (empty($this->products)) {
            return 0;
        }

        return array_sum(array_column($this->products, 'quantity'));
    }

    /**
     * Funcion para obtener todos los productos asociados con modelos
     */
    public function getProductsModelsAttribute()
    {
        if (empty($this->products)) {
            return collect();
        }

        $productIds = collect($this->products)->pluck('product_id')->toArray();
        return Product::whereIn('id', $productIds)->get()->map(function ($product) {
            $productData = collect($this->products)->firstWhere('product_id', $product->id);
            $subtotal = $productData['sale_price'] * $productData['quantity'];
            $discountAmount = $subtotal * ($this->discount_percentage / 100);

            return [
                'product' => $product,
                'sale_price' => $productData['sale_price'],
                'quantity' => $productData['quantity'],
                'subtotal' => $subtotal,
                'final_price' => $subtotal - $discountAmount,
                'discount_amount' => $discountAmount,
            ];
        });
    }
}
