<?php

namespace App\Repository;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

class ProductLotsRepository
{




    public function checkTheLotWithTheLowestPrice(Product $product, Supplier $supplier): Model | null
    {
        $consulta = ProductLot::query()
            ->where("product_id", "=", $product->id)
            ->where("supplier_id", "=", $supplier->id)
            ->orderBy("unit_cost", "DESC")
            ->first();
        return $consulta;
    }
}
