<?php

declare(strict_types=1);

namespace App\Services\Restaurant;

use App\Contracts\KdsServiceInterface;
use App\Enums\KitchenStatusEnum;
use App\Models\Order;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class KdsService implements KdsServiceInterface
{
    /**
     * @inheritDoc
     */
    public function sendOrderToKitchen(Order $order, ?array $detailIds = null): array
    {
        return DB::transaction(function () use ($order, $detailIds) {
            $query = $order->details()->where('product_type', 'dish');

            if (!empty($detailIds)) {
                $query->whereIn('id', $detailIds);
            } else {
                $query->where(function ($q) {
                    $q->whereNull('kitchen_status')
                      ->orWhere('kitchen_status', KitchenStatusEnum::DRAFT->value);
                });
            }

            $details = $query->get();
            $now = Carbon::now();
            $commandedCount = 0;

            foreach ($details as $detail) {
                $detail->update([
                    'kitchen_status' => KitchenStatusEnum::QUEUED->value,
                    'commanded_at' => $detail->commanded_at ?? $now,
                ]);
                $commandedCount++;
            }

            return [
                'order_id' => $order->id,
                'commanded_count' => $commandedCount,
                'timestamp' => $now->toDateTimeString(),
            ];
        });
    }

    /**
     * @inheritDoc
     */
    public function getActiveTickets(?int $stationId = null): Collection
    {
        $activeStatuses = [
            KitchenStatusEnum::QUEUED->value,
            KitchenStatusEnum::PREPARING->value,
        ];

        return Order::query()
            ->with([
                'client:id,name',
                'seller:id,name',
                'diningTable:id,name',
                'details' => function ($q) use ($stationId, $activeStatuses) {
                    $q->where('product_type', 'dish')
                      ->whereIn('kitchen_status', $activeStatuses);

                    if ($stationId) {
                        $q->whereHas('dish', function ($dishQ) use ($stationId) {
                            $dishQ->where('kitchen_station_id', $stationId);
                        });
                    }

                    $q->with([
                        'dish.kitchenStation',
                        'dish.category:id,name',
                        'dish.ingredients' => function ($ingQ) {
                            $ingQ->wherePivot('is_removable', true);
                        },
                        'modifiers',
                    ])
                    ->orderBy('course_number', 'asc')
                    ->orderBy('commanded_at', 'asc');
                },
            ])
            ->whereHas('details', function ($q) use ($stationId, $activeStatuses) {
                $q->where('product_type', 'dish')
                  ->whereIn('kitchen_status', $activeStatuses);

                if ($stationId) {
                    $q->whereHas('dish', function ($dishQ) use ($stationId) {
                        $dishQ->where('kitchen_station_id', $stationId);
                    });
                }
            })
            ->whereNotIn('status', [Order::COMPLETED, Order::CANCELLED])
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function updateItemStatus(OrderDetail $detail, KitchenStatusEnum $status): OrderDetail
    {
        return DB::transaction(function () use ($detail, $status) {
            $data = ['kitchen_status' => $status->value];

            if ($status === KitchenStatusEnum::READY) {
                $data['prepared_at'] = Carbon::now();
            }

            $detail->update($data);

            return $detail->fresh(['dish.kitchenStation', 'modifiers']);
        });
    }

    /**
     * @inheritDoc
     */
    public function bumpTicket(Order $order, ?int $stationId = null): Order
    {
        return DB::transaction(function () use ($order, $stationId) {
            $query = $order->details()
                ->where('product_type', 'dish')
                ->whereIn('kitchen_status', [
                    KitchenStatusEnum::QUEUED->value,
                    KitchenStatusEnum::PREPARING->value,
                ]);

            if ($stationId) {
                $query->whereHas('dish', function ($dishQ) use ($stationId) {
                    $dishQ->where('kitchen_station_id', $stationId);
                });
            }

            $now = Carbon::now();
            foreach ($query->get() as $detail) {
                $detail->update([
                    'kitchen_status' => KitchenStatusEnum::READY->value,
                    'prepared_at' => $now,
                ]);
            }

            return $order->fresh(['details.dish.kitchenStation']);
        });
    }
}
