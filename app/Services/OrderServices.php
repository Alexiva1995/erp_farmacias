<?php


namespace App\Services;

use App\Contracts\Order;
use App\Repository\OrderRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderServices implements Order
{


    public function __construct(
        protected OrderRepository $orderRepository
    ) {}

    public function filtrarOrdenesWithPsychotropicsforPaginate(array $filtros): LengthAwarePaginator
    {
        return $this->orderRepository->filtrarOrdenesWithPsychotropicsforPaginate($filtros, $filtros["itemsPerPage"]);
    }
}
