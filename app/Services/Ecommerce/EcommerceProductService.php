<?php

namespace App\Services\Ecommerce;

use App\Models\Product;
use Exception;

class EcommerceProductService
{
    /**
     * Alternar el estado de favorito de un producto en la base de datos.
     *
     * @param int $id ID del producto.
     * @return Product
     * @throws Exception
     */
    public function toggleFavorite(int $id): Product
    {
        $product = Product::find($id);

        if (!$product) {
            throw new Exception("El producto con ID {$id} no existe.");
        }

        $product->is_favorite = !$product->is_favorite;
        $product->save();

        return $product;
    }
}
