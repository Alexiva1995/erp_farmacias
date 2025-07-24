<?php

namespace App\Services;

use App\Contracts\Product;
use App\Repository\ProductRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductServices implements Product
{
    public function __construct(
        protected ProductRepository $productRepository
    ) {}

    public function filtrarStock(array $filtros): LengthAwarePaginator
    {
        return $this->productRepository->filtrarProductforStocktWithPaginate($filtros, $filtros["itemsPerPage"]);
    }
}
