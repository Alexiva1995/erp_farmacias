<?php

namespace App\Services;

use App\Contracts\ProductSupplier;
use App\Models\Product;
use App\Repository\ProductSupplierRepository;
use Illuminate\Database\Eloquent\Collection;

class ProductSupplierServices implements ProductSupplier
{

    public function __construct(protected ProductSupplierRepository $productSupplierRepository) {}


    public function consultSupplierByProductWithBetterPrice(Product $product): Collection
    {
        return $this->productSupplierRepository->consultSupplierByProductWithBetterPrice($product->id);
    }
}
