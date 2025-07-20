<?php


namespace App\Services;

use App\Contracts\Lottery;
use App\Repository\OrderRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LotteryServices implements Lottery
{

    public function __construct(
        protected OrderRepository $orderRepository
    ) {}

    public function filterOrdersWithoutPaginate(array $filtros): Collection
    {
        return $this->orderRepository->filtrarOrdenesforLotteryWithoutPaginate($filtros);
    }


    public function filterOrdersPaginate(array $filtros, $perPage = 10): LengthAwarePaginator
    {
        return $this->orderRepository->filtrarOrdenesforLotteryWithPaginate($filtros, $perPage);
    }
}
