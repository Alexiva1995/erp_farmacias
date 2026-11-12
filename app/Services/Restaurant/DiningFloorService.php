<?php

namespace App\Services\Restaurant;

use App\Contracts\DiningFloorServiceInterface;
use App\Enums\DiningTableStatusEnum;
use App\Enums\KitchenStatusEnum;
use App\Models\Client;
use App\Models\DiningTable;
use App\Models\DiningZone;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class DiningFloorService implements DiningFloorServiceInterface
{
    public function getFloorOverview(?int $zoneId = null): array
    {
        // Auto-crear zona inicial base si el restaurante aún no tiene ninguna
        if (DiningZone::count() === 0) {
            DiningZone::create([
                'name' => 'Salón Principal',
                'background_style' => 'wooden',
                'width_meters' => 12.00,
                'height_meters' => 8.00,
                'layout_config' => [
                    'areas' => [
                        ['id' => 'kitchen_1', 'type' => 'kitchen', 'name' => 'Cocina', 'x' => 1, 'y' => 1, 'w' => 4, 'h' => 3],
                        ['id' => 'pass_1', 'type' => 'pass_counter', 'name' => 'Mostrador de Pase', 'x' => 1, 'y' => 4, 'w' => 4, 'h' => 1],
                        ['id' => 'storage_1', 'type' => 'storage', 'name' => 'Depósito / Almacén', 'x' => 9, 'y' => 1, 'w' => 2, 'h' => 3],
                    ],
                ],
                'sort_order' => 1,
                'is_active' => true,
            ]);
        }

        $zonesQuery = DiningZone::where('is_active', true)->orderBy('sort_order');
        if ($zoneId) {
            $zonesQuery->where('id', $zoneId);
        }
        $zones = $zonesQuery->get();

        $tablesQuery = DiningTable::with([
            'currentOrder.details.dish.kitchenStation',
            'currentOrder.seller:id,username,name',
            'currentOrder.client:id,name,identification',
        ])->where('is_active', true);

        if ($zoneId) {
            $tablesQuery->where('dining_zone_id', $zoneId);
        }
        $tables = $tablesQuery->get();

        $stations = collect();
        if (Schema::hasTable('kitchen_stations')) {
            $stationsCollection = KitchenStation::where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $activeDetails = OrderDetail::query()
                ->whereIn('kitchen_status', [
                    KitchenStatusEnum::QUEUED->value,
                    KitchenStatusEnum::PREPARING->value,
                    KitchenStatusEnum::READY->value,
                ])
                ->with([
                    'dish:id,name,kitchen_station_id',
                    'order.diningTable:id,name',
                ])
                ->get();

            $groupedByStation = $activeDetails->groupBy(fn ($item) => $item->dish?->kitchen_station_id ?? 0);

            $stations = $stationsCollection->map(function ($st) use ($groupedByStation) {
                $items = $groupedByStation->get($st->id, collect());
                $st->queued_count = $items->where('kitchen_status', KitchenStatusEnum::QUEUED->value)->count();
                $st->preparing_count = $items->where('kitchen_status', KitchenStatusEnum::PREPARING->value)->count();
                $st->ready_count = $items->where('kitchen_status', KitchenStatusEnum::READY->value)->count();
                $st->active_items = $items->map(fn ($it) => [
                    'id' => $it->id,
                    'dish_name' => $it->dish?->name ?? 'Platillo',
                    'table_name' => $it->order?->diningTable?->name ?? 'Salón',
                    'status' => $it->kitchen_status,
                ])->values()->all();
                return $st;
            });
        }

        return [
            'zones' => $zones,
            'tables' => $tables,
            'kitchen_stations' => $stations,
        ];
    }

    public function createZone(array $data): DiningZone
    {
        return DiningZone::create([
            'name' => $data['name'],
            'background_style' => $data['background_style'] ?? 'grid',
            'width_meters' => $data['width_meters'] ?? 12.00,
            'height_meters' => $data['height_meters'] ?? 8.00,
            'layout_config' => $data['layout_config'] ?? null,
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
                $updateData = [
                    'pos_x' => $item['pos_x'] ?? 0,
                    'pos_y' => $item['pos_y'] ?? 0,
                    'width' => $item['width'] ?? 80,
                    'height' => $item['height'] ?? 80,
                    'rotation' => $item['rotation'] ?? 0,
                ];
                if (isset($item['capacity'])) {
                    $updateData['capacity'] = (int) $item['capacity'];
                }
                DiningTable::where('id', $item['id'])->update($updateData);
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
