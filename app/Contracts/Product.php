<?php


namespace App\Contracts;

use App\Exports\StockProductExport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface Product
{

    public function filtrarStock(array $filtros): LengthAwarePaginator;
    public function filtrarStockWithoutPaginate(array $filtros): Collection;
    public function exportExcel(array $filtros): StockProductExport;
}
