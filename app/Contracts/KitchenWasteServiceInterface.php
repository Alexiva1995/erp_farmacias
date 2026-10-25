<?php

namespace App\Contracts;

use App\Models\KitchenWaste;

interface KitchenWasteServiceInterface
{
    public function listWastes(array $filters = []): array;

    public function recordWaste(array $data, int $userId): KitchenWaste;
}
