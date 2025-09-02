<?php

namespace App\Contracts;

use App\Models\AutoOrder as ModelsAutoOrder;
use Illuminate\Pagination\LengthAwarePaginator;

interface AutoOrder
{
    public function create(array $order): ModelsAutoOrder;
    public function createMultiple(array $orders): array;
    public function getAll(array $filters = []): LengthAwarePaginator;
}
