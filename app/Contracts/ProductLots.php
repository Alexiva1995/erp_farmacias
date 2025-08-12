<?php

namespace App\Contracts;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

interface ProductLots
{


    public function checkTheLotWithTheLowestPrice(Product $producto, Supplier $supplier): Model | null;
}
