<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;


interface Lottery
{


    public function filterOrdersWithoutPaginate(array $filtros): Collection;

    public function filterOrdersPaginate(array $filtros, int $perPage): LengthAwarePaginator;
}
