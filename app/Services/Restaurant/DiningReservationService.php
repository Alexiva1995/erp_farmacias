<?php

namespace App\Services\Restaurant;

use App\Contracts\DiningReservationServiceInterface;
use App\Enums\DiningTableStatusEnum;
use App\Models\Client;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DiningReservationService implements DiningReservationServiceInterface
{
    public function listReservations(array $filters = []): array
    {
        $query = Reservation::with(['client', 'diningTable.zone'])
            ->whereNotNull('dining_table_id')
            ->latest('date');

        if (!empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15)->toArray();
    }

    public function createReservation(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $table = DiningTable::findOrFail($data['dining_table_id']);
            $guests = (int) ($data['guests_count'] ?? 2);

            if ($guests > $table->capacity + 2) {
                throw new InvalidArgumentException("La capacidad máxima de la mesa {$table->name} es de {$table->capacity} comensales.");
            }

            // Buscar o crear cliente
            $clientId = $data['client_id'] ?? null;
            if (!$clientId && !empty($data['client_name'])) {
                $client = Client::firstOrCreate(
                    ['name' => $data['client_name']],
                    [
                        'identification' => $data['identification'] ?? 'V-' . rand(10000000, 99999999),
                        'identification_type' => 'V-',
                        'phone' => $data['client_whatsapp'] ?? null,
                    ]
                );
                $clientId = $client->id;
            }

            $reservation = Reservation::create([
                'dining_table_id' => $table->id,
                'client_id' => $clientId,
                'client_name' => $data['client_name'] ?? 'Cliente Reserva',
                'identification' => $data['identification'] ?? '',
                'client_whatsapp' => $data['client_whatsapp'] ?? null,
                'date' => $data['date'] ?? Carbon::today(),
                'start_time' => $data['start_time'] ?? '19:00',
                'end_time' => $data['end_time'] ?? '21:00',
                'guests_count' => $guests,
                'special_requests' => $data['special_requests'] ?? null,
                'deposit_amount_usd' => $data['deposit_amount_usd'] ?? 0,
                'status' => 'confirmed',
            ]);

            // Marcar mesa en estado reservada si la reserva es para hoy
            if (Carbon::parse($reservation->date)->isToday()) {
                $table->update(['status' => DiningTableStatusEnum::RESERVED]);
            }

            return $reservation;
        });
    }

    public function checkIn(Reservation $reservation, int $sellerId): Order
    {
        return DB::transaction(function () use ($reservation, $sellerId) {
            $table = $reservation->diningTable;
            if (!$table) {
                throw new InvalidArgumentException('La reserva no tiene una mesa de salón asociada.');
            }

            // Crear orden abierta para la mesa
            $order = Order::create([
                'client_id' => $reservation->client_id ?: 1,
                'seller_id' => $sellerId,
                'order_date' => Carbon::now(),
                'status' => Order::PENDING,
                'currency' => 'USD',
                'total_amount' => 0,
                'total_amount_usd' => 0,
                'total_cost' => 0,
                'money_returns' => 0,
                'usd_conversion' => 1,
                'dining_table_id' => $table->id,
                'guests_count' => $reservation->guests_count ?: 2,
            ]);

            $table->update([
                'status' => DiningTableStatusEnum::OCCUPIED,
                'current_order_id' => $order->id,
            ]);

            $reservation->update(['status' => 'completed']);

            return $order;
        });
    }

    public function cancelReservation(Reservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => 'cancelled']);
            if ($reservation->diningTable && $reservation->diningTable->status === DiningTableStatusEnum::RESERVED) {
                $reservation->diningTable->update(['status' => DiningTableStatusEnum::AVAILABLE]);
            }
            return true;
        });
    }
}
