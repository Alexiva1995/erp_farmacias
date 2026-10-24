<?php

namespace App\Contracts;

use App\Models\DiningTable;
use App\Models\DiningZone;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface DiningFloorServiceInterface
{
    public function getFloorOverview(?int $zoneId = null): array;

    public function createZone(array $data): DiningZone;

    public function updateZone(DiningZone $zone, array $data): DiningZone;

    public function deleteZone(DiningZone $zone): bool;

    public function createTable(array $data): DiningTable;

    public function updateTable(DiningTable $table, array $data): DiningTable;

    public function deleteTable(DiningTable $table): bool;

    public function updateLayout(array $tablesLayout): bool;

    public function openTableOrder(DiningTable $table, int $sellerId, int $guestsCount = 1, ?int $clientId = null): Order;

    public function transferOrMergeTable(DiningTable $sourceTable, DiningTable $targetTable): DiningTable;

    public function releaseTable(DiningTable $table): DiningTable;
}
