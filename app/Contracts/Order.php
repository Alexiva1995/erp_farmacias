<?php


namespace App\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface Order
{

    public function filtrarOrdenesWithPsychotropicsforPaginate(array $filtros): LengthAwarePaginator;
}
