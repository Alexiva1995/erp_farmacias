<?php

namespace App\Rules;

use App\Models\Product;
use App\Models\ProductLot;
use Illuminate\Contracts\Validation\Rule;

class ValidateLotQuantity implements Rule
{
    protected $productId;
    protected $lotId;
    protected $message;

    public function __construct($productId, $lotId = null)
    {
        $this->productId = $productId;
        $this->lotId = $lotId;
    }

    public function passes($attribute, $value)
    {
        $product = Product::find($this->productId);
        if (!$product) {
            $this->message = 'Producto no encontrado.';
            return false;
        }

        $currentLotsSum = ProductLot::where('product_id', $this->productId)
            ->when($this->lotId, function ($query) {
                $query->where('id', '!=', $this->lotId);
            })
            ->sum('quantity');

        if ($currentLotsSum == $product->stock) {
            return true;
        }

        if ($currentLotsSum < $product->stock) {
            $availableQuantity = $product->stock - $currentLotsSum;

            if ($value > $availableQuantity) {
                $this->message = "La cantidad no puede exceder {$availableQuantity} unidades. Stock disponible: {$availableQuantity}.";
                return false;
            }
        }

        return true;
    }

    public function message()
    {
        return $this->message ?? 'La cantidad del lote no es válida.';
    }
}
