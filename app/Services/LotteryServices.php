<?php


namespace App\Services;

use App\Contracts\Lottery;
use App\Repository\OrderRepository;
use Illuminate\Database\Eloquent\Collection;

class LotteryServices implements Lottery
{

    public function __construct(
        protected OrderRepository $orderRepository
    ) {}


    public function filterOrders(array $filtros): Collection
    {
        return $this->orderRepository->filtrarOrdenesforLottery($filtros);
    }
}
