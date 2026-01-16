<?php

namespace App\Contracts;

use App\Models\AutoOrder as ModelsAutoOrder;

interface AutoOrder
{
    public function create(array $order): ModelsAutoOrder;
    public function createMultiple(array $orders, array $withoutSupplierIds = []): array;
    public function getMarkedProductsWithoutSupplier(int $perPage, string $sortBy, string $order);
}
