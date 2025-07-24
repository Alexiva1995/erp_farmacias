<?php


namespace App\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface Product
{

    public function filtrarStock(array $filtros): LengthAwarePaginator;
}
