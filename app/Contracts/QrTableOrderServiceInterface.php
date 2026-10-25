<?php

namespace App\Contracts;

use App\Models\DiningTable;
use App\Models\Order;

interface QrTableOrderServiceInterface
{
    public function getTableByToken(string $token): array;

    public function submitQrOrder(string $token, array $data): Order;

    public function requestAssistance(string $token, string $reason): DiningTable;
}
