<?php


namespace App\Services;

use App\Contracts\ProductLots;
use App\Models\Product;
use App\Models\Supplier;
use App\Repository\ProductLotsRepository;
use Illuminate\Database\Eloquent\Model;

class ProductLotsServices implements ProductLots
{

    public function __construct(protected ProductLotsRepository $productLotsRepository) {}

    public function checkTheLotWithTheLowestPrice(Product $product, Supplier $supplier): ?Model
    {
        return $this->productLotsRepository->checkTheLotWithTheLowestPrice($product, $supplier);
    }
}
