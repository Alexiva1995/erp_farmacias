<?php

namespace App\Services;

use App\Contracts\AutoOrder;
use App\Models\AutoOrder as ModelsAutoOrder;
use App\Models\Supplier;
use App\Repository\AutoOrderDetailsRepository;
use App\Repository\AutoOrdersRepository;
use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Request;

class AutoOrderServices implements AutoOrder
{
    public function __construct(
        protected AutoOrdersRepository $autoOrdersRepository,
        protected AutoOrderDetailsRepository $autoOrderDetailsRepository,
    ) {}

    public function create(array $order): ModelsAutoOrder
    {
        $today = new DateTime("now");
        $order["order_date"] = $today->format("Y-m-d");
        $autoOrder = $this->autoOrdersRepository->create($order);
        foreach ($order["details"] as $key => $detail) {
            # code...
            $detail["order_id"] = $autoOrder->id;
            $autoOrderDetail = $this->autoOrderDetailsRepository->create($detail);
        }
        $autoOrder->details;
        return $autoOrder;
    }

    public function createMultiple(array $orders): array
    {
        $listAutoOrders = [];

        foreach ($orders as $key => $order) {
            $listAutoOrders[] = $this->create($order);
        }

        return $listAutoOrders;
    }
}
