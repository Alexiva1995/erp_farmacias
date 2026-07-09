<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

class ProductLotsRepository implements \App\Contracts\ProductLots
{




    public function checkTheLotWithTheLowestPrice(Product $product, Supplier $supplier): Model | null
    {
        $consulta = ProductLot::query()
            ->where("product_id", "=", $product->id)
            ->where("supplier_id", "=", $supplier->id)
            ->orderBy("unit_cost", "ASC")
            ->first();
        return $consulta;
    }

    public function checkTheLotWithTheLowestPriceOnlyProduct(Product $product): Model | null
    {
        $consulta = ProductLot::query()
            ->where("product_id", "=", $product->id)
            ->orderBy("unit_cost", "ASC")
            ->first();
        return $consulta;
    }
}
