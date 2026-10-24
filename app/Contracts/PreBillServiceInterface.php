<?php

namespace App\Contracts;

use App\Models\Order;

interface PreBillServiceInterface
{
    public function generatePreBill(Order $order, float $servicePercentage = 10.0): array;

    public function cancelPreBill(Order $order): bool;
}
