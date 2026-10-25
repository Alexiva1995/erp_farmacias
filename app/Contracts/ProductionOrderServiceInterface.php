<?php

namespace App\Contracts;

use App\Models\ProductionOrder;

interface ProductionOrderServiceInterface
{
    public function listOrders(array $filters = []): array;

    public function createAndExecute(array $data, int $userId): ProductionOrder;

    public function cancelOrder(ProductionOrder $productionOrder): bool;
}
