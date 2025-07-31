<?php

namespace App\Services;

use App\Contracts\Product;
use App\Exports\StockProductExport;
use App\Repository\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
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

    public function filtrarStockWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarProductforStocktWithoutPaginate($filtros);
    }

    public function exportExcel(array $filtros): StockProductExport
    {
        $query = $this->productRepository->builerFiltrarProductforStock($filtros);
        return new StockProductExport($query);
    }

    public function filtrarIaOrderAssistantTypeAverage(array $filtros): LengthAwarePaginator
    {
        return $this->productRepository->filtrarProductforIaOrderAssistantTypeAverageWithPaginate($filtros, $filtros["itemsPerPage"]);
    }

    public function filtrarIaOrderAssistantTypeAverageWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarProductforIaOrderAssistantTypeAverageWithoutPaginate($filtros);
    }
    // 
    public function filtrarIaOrderAssistantTypeSales(array $filtros): LengthAwarePaginator
    {
        return $this->productRepository->filtrarProductforIaOrderAssistantTypeSalesWithPaginate($filtros, $filtros["itemsPerPage"]);
    }

    public function filtrarIaOrderAssistantTypeSalesWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarProductforIaOrderAssistantTypeSalesWithoutPaginate($filtros);
    }
}
