<?php

namespace App\Services\Restaurant;

use App\Contracts\DiningFloorServiceInterface;
use App\Enums\DiningTableStatusEnum;
use App\Models\Client;
use App\Models\DiningTable;
use App\Models\DiningZone;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DiningFloorService implements DiningFloorServiceInterface
{
    public function getFloorOverview(?int $zoneId = null): array
    {
        $zonesQuery = DiningZone::where('is_active', true)->orderBy('sort_order');
        if ($zoneId) {
            $zonesQuery->where('id', $zoneId);
        }
        $zones = $zonesQuery->get();

        $tablesQuery = DiningTable::with([
            'currentOrder.details.dish',
            'currentOrder.seller:id,name',
            'currentOrder.client:id,name,identification',
        ])->where('is_active', true);

        if ($zoneId) {
            $tablesQuery->where('dining_zone_id', $zoneId);
        }
        $tables = $tablesQuery->get();

        return [
            'zones' => $zones,
            'tables' => $tables,
        ];
    }

    public function createZone(array $data): DiningZone
    {
        return DiningZone::create([
            'name' => $data['name'],
            'background_style' => $data['background_style'] ?? 'grid',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateZone(DiningZone $zone, array $data): DiningZone
    {
        $zone->update($data);
        return $zone;
    }

    public function deleteZone(DiningZone $zone): bool
    {
        return (bool) $zone->delete();
    }

    public function createTable(array $data): DiningTable
    {
        return DiningTable::create([
            'dining_zone_id' => $data['dining_zone_id'],
            'name' => $data['name'],
            'capacity' => $data['capacity'] ?? 4,
            'shape' => $data['shape'] ?? 'square',
            'pos_x' => $data['pos_x'] ?? 100,
            'pos_y' => $data['pos_y'] ?? 100,
            'width' => $data['width'] ?? 80,
            'height' => $data['height'] ?? 80,
            'rotation' => $data['rotation'] ?? 0,
            'status' => DiningTableStatusEnum::AVAILABLE,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateTable(DiningTable $table, array $data): DiningTable
    {
        $table->update($data);
        return $table;
    }

    public function deleteTable(DiningTable $table): bool
    {
        return (bool) $table->delete();
    }

    public function updateLayout(array $tablesLayout): bool
    {
        return DB::transaction(function () use ($tablesLayout) {
            foreach ($tablesLayout as $item) {
                if (!isset($item['id'])) {
                    continue;
                }
                DiningTable::where('id', $item['id'])->update([
                    'pos_x' => $item['pos_x'] ?? 0,
                    'pos_y' => $item['pos_y'] ?? 0,
                    'width' => $item['width'] ?? 80,
                    'height' => $item['height'] ?? 80,
                    'rotation' => $item['rotation'] ?? 0,
                ]);
            }
            return true;
        });
    }

    public function openTableOrder(DiningTable $table, int $sellerId, int $guestsCount = 1, ?int $clientId = null): Order
    {
        return DB::transaction(function () use ($table, $sellerId, $guestsCount, $clientId) {
            // Si no se especifica cliente, usar el cliente consumidor final o primero disponible
            if (!$clientId) {
                $defaultClient = Client::first();
                $clientId = $defaultClient ? $defaultClient->id : 1;
            }

            // Crear orden abierta
            $order = Order::create([
                'client_id' => $clientId,
                'seller_id' => $sellerId,
                'order_date' => now(),
                'status' => Order::PENDING,
                'currency' => 'USD',
                'total_amount' => 0,
                'total_amount_usd' => 0,
                'total_cost' => 0,
                'money_returns' => 0,
                'usd_conversion' => 1,
                'dining_table_id' => $table->id,
                'guests_count' => $guestsCount,
            ]);

            $table->update([
                'status' => DiningTableStatusEnum::OCCUPIED,
                'current_order_id' => $order->id,
            ]);

            return $order;
        });
    }

    public function transferOrMergeTable(DiningTable $sourceTable, DiningTable $targetTable): DiningTable
    {
        return DB::transaction(function () use ($sourceTable, $targetTable) {
            $sourceOrder = $sourceTable->currentOrder;
            if (!$sourceOrder) {
                throw new InvalidArgumentException('La mesa origen no tiene una orden activa.');
            }

            $targetOrder = $targetTable->currentOrder;

            if ($targetOrder) {
                // Fusión de órdenes: mover detalles de origen a destino
                foreach ($sourceOrder->details as $detail) {
                    $detail->update(['order_id' => $targetOrder->id]);
                }
                $targetOrder->recalculateTotals();

                // Cancelar orden origen
                $sourceOrder->update([
                    'status' => Order::CANCELLED,
                    'dining_table_id' => null,
                ]);
            } else {
                // Transferencia limpia de mesa
                $sourceOrder->update([
                    'dining_table_id' => $targetTable->id,
                ]);
                $targetTable->update([
                    'status' => DiningTableStatusEnum::OCCUPIED,
                    'current_order_id' => $sourceOrder->id,
                ]);
            }

            // Liberar mesa origen
            $sourceTable->update([
                'status' => DiningTableStatusEnum::AVAILABLE,
                'current_order_id' => null,
            ]);

            return $targetTable->fresh(['currentOrder.details']);
        });
    }

    public function releaseTable(DiningTable $table): DiningTable
    {
        $table->update([
            'status' => DiningTableStatusEnum::AVAILABLE,
            'current_order_id' => null,
        ]);
        return $table;
    }
}
