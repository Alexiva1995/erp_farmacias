<?php

namespace App\Services\Restaurant;

use App\Contracts\RestaurantSimulatorServiceInterface;
use App\Enums\DiningTableStatusEnum;
use App\Enums\KitchenStatusEnum;
use App\Models\Client;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RestaurantLiveSimulatorService implements RestaurantSimulatorServiceInterface
{
    public function triggerSingleOrder(): array
    {
        return DB::transaction(function () {
            // 1. Buscar una mesa disponible o cualquiera si todas están ocupadas
            $table = DiningTable::where('is_active', true)
                ->where('status', DiningTableStatusEnum::AVAILABLE)
                ->inRandomOrder()
                ->first();

            $isNewOrder = true;
            $order = null;

            if ($table) {
                $client = Client::inRandomOrder()->first();
                if (!$client) {
                    $client = Client::firstOrCreate(
                        ['identification' => '00000000'],
                        [
                            'name' => 'Cliente Ocasional',
                            'last_name' => 'Salón',
                            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO, // 'V-'
                            'email' => 'cliente_restaurante@tovaerp.com',
                            'phone' => '04120000000',
                            'address' => 'Salón Principal',
                        ]
                    );
                }

                $seller = User::inRandomOrder()->first();
                if (!$seller) {
                    $seller = User::first();
                }

                $order = Order::create([
                    'client_id' => $client->id,
                    'seller_id' => $seller?->id ?? 1,
                    'order_date' => Carbon::now(),
                    'status' => Order::PENDING,
                    'currency' => 'USD',
                    'total_amount' => 0,
                    'total_amount_usd' => 0,
                    'total_cost' => 0,
                    'money_returns' => 0,
                    'usd_conversion' => 1,
                    'dining_table_id' => $table->id,
                    'guests_count' => rand(1, min($table->capacity, 4)),
                ]);

                $table->update([
                    'status' => DiningTableStatusEnum::OCCUPIED,
                    'current_order_id' => $order->id,
                    'needs_attention' => false,
                    'attention_reason' => null,
                ]);
            } else {
                // Si no hay mesas libres, buscar una mesa ocupada para agregar una segunda ronda
                $table = DiningTable::where('is_active', true)
                    ->whereNotNull('current_order_id')
                    ->inRandomOrder()
                    ->first();

                if ($table && $table->currentOrder) {
                    $order = $table->currentOrder;
                    $isNewOrder = false;
                }
            }

            if (!$order) {
                return ['success' => false, 'message' => 'No hay mesas disponibles para simular orden.'];
            }

            // 2. Elegir entre 1 y 3 platillos al azar
            $dishes = Dish::where('status', 1)->inRandomOrder()->take(rand(1, 3))->get();
            if ($dishes->isEmpty()) {
                $dishes = Dish::inRandomOrder()->take(2)->get();
            }

            $addedItems = [];
            foreach ($dishes as $dish) {
                $qty = rand(1, 2);
                $price = (float) ($dish->designated_price ?: $dish->suggested_price ?: 10.0);
                $cost = (float) ($dish->cost_price ?: 4.0);

                $detail = OrderDetail::create([
                    'order_id' => $order->id,
                    'dish_id' => $dish->id,
                    'product_type' => 'dish',
                    'quantity' => $qty,
                    'price' => $price,
                    'unit_price_usd' => $price,
                    'unit_cost' => $cost,
                    'seat_number' => rand(1, max(1, $order->guests_count ?? 2)),
                    'notes' => rand(0, 1) ? 'Término medio / sin sal extra' : null,
                    'kitchen_status' => KitchenStatusEnum::QUEUED->value,
                    'course_number' => rand(1, 2),
                    'commanded_at' => Carbon::now(),
                ]);

                $addedItems[] = "{$qty}x {$dish->name}";
            }

            $order->recalculateTotals();

            return [
                'success' => true,
                'event' => $isNewOrder ? 'order_created' : 'items_added',
                'table_name' => $table?->name ?? 'Mesa',
                'order_id' => $order->id,
                'items' => $addedItems,
                'total_usd' => (float) $order->total_amount_usd,
                'message' => $isNewOrder 
                    ? "Nueva orden abierta en {$table->name} con " . implode(', ', $addedItems)
                    : "Segunda ronda en {$table->name}: " . implode(', ', $addedItems),
            ];
        });
    }

    public function advanceKitchenCycle(): array
    {
        // 1. Buscar platillos en preparación para marcarlos listos
        $preparingItem = OrderDetail::where('kitchen_status', KitchenStatusEnum::PREPARING)
            ->with(['dish', 'order.diningTable'])
            ->oldest('commanded_at')
            ->first();

        if ($preparingItem) {
            $preparingItem->update([
                'kitchen_status' => KitchenStatusEnum::READY,
                'prepared_at' => Carbon::now(),
            ]);

            return [
                'success' => true,
                'event' => 'item_ready',
                'item_name' => $preparingItem->dish?->name ?? 'Platillo',
                'table_name' => $preparingItem->order?->diningTable?->name ?? 'Salón',
                'message' => "¡Plato listo para servir! {$preparingItem->dish?->name} ({$preparingItem->order?->diningTable?->name})",
            ];
        }

        // 2. Si no hay en preparación, tomar uno de la cola y ponerlo a cocinar
        $queuedItem = OrderDetail::where('kitchen_status', KitchenStatusEnum::QUEUED)
            ->with(['dish', 'order.diningTable'])
            ->oldest('commanded_at')
            ->first();

        if ($queuedItem) {
            $queuedItem->update([
                'kitchen_status' => KitchenStatusEnum::PREPARING,
            ]);

            return [
                'success' => true,
                'event' => 'item_cooking',
                'item_name' => $queuedItem->dish?->name ?? 'Platillo',
                'table_name' => $queuedItem->order?->diningTable?->name ?? 'Salón',
                'message' => "Cocina marchando: {$queuedItem->dish?->name} para {$queuedItem->order?->diningTable?->name}",
            ];
        }

        return ['success' => false, 'message' => 'No hay ítems pendientes en cocina.'];
    }

    public function triggerGuestAction(): array
    {
        // Mesa ocupada que pida la cuenta o llame al mesero
        $table = DiningTable::where('is_active', true)
            ->where('status', DiningTableStatusEnum::OCCUPIED)
            ->inRandomOrder()
            ->first();

        if (!$table) {
            return ['success' => false, 'message' => 'No hay mesas ocupadas para simular comensales.'];
        }

        $actions = ['bill_requested', 'waiter_call'];
        $chosen = $actions[array_rand($actions)];

        if ($chosen === 'bill_requested') {
            $table->update([
                'status' => DiningTableStatusEnum::BILL_REQUESTED,
                'needs_attention' => true,
                'attention_reason' => 'bill_requested',
            ]);
            return [
                'success' => true,
                'event' => 'bill_requested',
                'table_name' => $table->name,
                'message' => "{$table->name} ha solicitado la cuenta.",
            ];
        } else {
            $table->update([
                'needs_attention' => true,
                'attention_reason' => 'waiter_call',
            ]);
            return [
                'success' => true,
                'event' => 'waiter_called',
                'table_name' => $table->name,
                'message' => "{$table->name} está llamando al mesero desde el código QR.",
            ];
        }
    }

    public function runFullCycle(): array
    {
        $results = [];
        $results['order'] = $this->triggerSingleOrder();
        $results['kitchen'] = $this->advanceKitchenCycle();
        if (rand(1, 100) <= 60) {
            $results['guest'] = $this->triggerGuestAction();
        }

        return [
            'success' => true,
            'cycle_summary' => $results,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }

    public function resetSimulation(): array
    {
        return DB::transaction(function () {
            // Cancelar órdenes abiertas de mesas simuladas
            Order::whereNotNull('dining_table_id')
                ->where('status', Order::PENDING)
                ->update(['status' => Order::CANCELLED]);

            // Devolver todas las mesas a disponible
            DiningTable::where('is_active', true)->update([
                'status' => DiningTableStatusEnum::AVAILABLE,
                'current_order_id' => null,
                'needs_attention' => false,
                'attention_reason' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Salón y mesas restablecidos a estado libre.',
            ];
        });
    }

    public function getStatus(): array
    {
        $totalTables = DiningTable::where('is_active', true)->count();
        $occupiedTables = DiningTable::where('is_active', true)->where('status', DiningTableStatusEnum::OCCUPIED)->count();
        $billRequested = DiningTable::where('is_active', true)->where('status', DiningTableStatusEnum::BILL_REQUESTED)->count();
        $queuedTickets = OrderDetail::where('kitchen_status', KitchenStatusEnum::QUEUED)->count();
        $preparingTickets = OrderDetail::where('kitchen_status', KitchenStatusEnum::PREPARING)->count();
        $readyTickets = OrderDetail::where('kitchen_status', KitchenStatusEnum::READY)->count();

        return [
            'total_tables' => $totalTables,
            'occupied_tables' => $occupiedTables,
            'bill_requested_tables' => $billRequested,
            'kitchen' => [
                'queued' => $queuedTickets,
                'preparing' => $preparingTickets,
                'ready' => $readyTickets,
            ],
            'server_time' => Carbon::now()->format('H:i:s'),
        ];
    }
}
