<?php

namespace App\Services\Restaurant;

use App\Contracts\QrTableOrderServiceInterface;
use App\Enums\DiningTableStatusEnum;
use App\Enums\KitchenStatusEnum;
use App\Models\Client;
use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QrTableOrderService implements QrTableOrderServiceInterface
{
    public function getTableByToken(string $token): array
    {
        $table = DiningTable::with(['zone', 'currentOrder.details.dish'])
            ->where('qr_token', $token)
            ->firstOrFail();

        return [
            'table_id' => $table->id,
            'table_name' => $table->name,
            'zone_name' => $table->zone?->name ?? 'Salón',
            'status' => is_object($table->status) ? $table->status->value : $table->status,
            'needs_attention' => (bool) $table->needs_attention,
            'current_order' => $table->currentOrder ? [
                'id' => $table->currentOrder->id,
                'total_amount_usd' => (float) $table->currentOrder->total_amount_usd,
                'items_count' => $table->currentOrder->details->count(),
                'details' => $table->currentOrder->details->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'name' => $d->dish?->name ?? $d->notes ?? 'Ítem',
                        'quantity' => (float) $d->quantity,
                        'unit_price_usd' => (float) $d->unit_price_usd,
                        'kitchen_status' => is_object($d->kitchen_status) ? $d->kitchen_status->value : $d->kitchen_status,
                    ];
                })->values()->toArray(),
            ] : null,
        ];
    }

    public function submitQrOrder(string $token, array $data): Order
    {
        return DB::transaction(function () use ($token, $data) {
            $table = DiningTable::where('qr_token', $token)->firstOrFail();
            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new InvalidArgumentException('Debe incluir al menos un plato o producto.');
            }

            // Buscar orden existente activa o crear una nueva para la mesa
            $order = $table->currentOrder;
            if (!$order || $order->status !== Order::PENDING) {
                $client = Client::first();
                $order = Order::create([
                    'client_id' => $client ? $client->id : 1,
                    'seller_id' => 1,
                    'order_date' => Carbon::now(),
                    'status' => Order::PENDING,
                    'currency' => 'USD',
                    'total_amount' => 0,
                    'total_amount_usd' => 0,
                    'total_cost' => 0,
                    'money_returns' => 0,
                    'usd_conversion' => 1,
                    'dining_table_id' => $table->id,
                    'guests_count' => $data['guests_count'] ?? 1,
                ]);

                $table->update([
                    'status' => DiningTableStatusEnum::OCCUPIED,
                    'current_order_id' => $order->id,
                ]);
            }

            // Agregar detalles a la comanda
            foreach ($items as $item) {
                $dish = Dish::findOrFail($item['dish_id']);
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($dish->designated_price ?: $dish->suggested_price ?: 0);

                OrderDetail::create([
                    'order_id' => $order->id,
                    'dish_id' => $dish->id,
                    'product_type' => 'dish',
                    'quantity' => $qty,
                    'price' => $price,
                    'unit_price_usd' => $price,
                    'unit_cost' => (float) ($dish->cost_price ?: $dish->cpv ?: 0),
                    'notes' => $item['notes'] ?? null,
                    'seat_number' => $item['seat_number'] ?? null,
                    'kitchen_status' => KitchenStatusEnum::QUEUED->value,
                    'course_number' => $item['course_number'] ?? 2,
                    'commanded_at' => Carbon::now(),
                ]);
            }

            $order->recalculateTotals();

            // Desmarcar llamado de atención y dejar alerta de pedido pendiente
            $table->update([
                'needs_attention' => true,
                'attention_reason' => 'qr_order_pending',
            ]);

            return $order->fresh(['details.dish']);
        });
    }

    public function requestAssistance(string $token, string $reason): DiningTable
    {
        $table = DiningTable::where('qr_token', $token)->firstOrFail();
        $table->update([
            'needs_attention' => true,
            'attention_reason' => $reason, // waiter_call, bill_requested
        ]);

        if ($reason === 'bill_requested') {
            $table->update(['status' => DiningTableStatusEnum::BILL_REQUESTED]);
        }

        return $table;
    }
}
