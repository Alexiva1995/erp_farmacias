<?php

namespace App\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductSupplier
{

    public function consultSupplierByProductWithBetterPrice(Product $product): Collection;
}
