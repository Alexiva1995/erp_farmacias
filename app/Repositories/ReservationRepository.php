<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Models\Court;
use App\Models\FixedSchedule;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReservationRepository implements ReservationRepositoryInterface
{
    /**
     * Obtener todas las canchas.
     */
    public function getAllCourts(): Collection
    {
        return Court::all();
    }

    /**
     * Obtener disponibilidad cruzando reservas del día y horarios fijos.
     */
    public function getAvailability(string $date): array
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeekIso; // 1 (Lunes) a 7 (Domingo)

        $courts = Court::all();
        $reservations = Reservation::where('date', $date)
            ->whereIn('status', ['pending', 'verified'])
            ->get();

        $fixedSchedules = FixedSchedule::where('day_of_week', $dayOfWeek)
            ->whereDoesntHave('exceptions', function ($query) use ($date) {
                $query->where('date', $date);
            })
            ->get();

        $result = [];

        foreach ($courts as $court) {
            $courtReservations = $reservations->where('court_id', $court->id);
            $courtFixedSchedules = $fixedSchedules->where('court_id', $court->id);

            $result[] = [
                'court' => $court,
                'reservations' => $courtReservations->values(),
                'fixed_schedules' => $courtFixedSchedules->values(),
            ];
        }

        return $result;
    }

    /**
     * Verificar si un bloque de horario está disponible para una cancha en una fecha.
     */
    public function checkAvailability(int $courtId, string $date, string $startTime, string $endTime, ?int $excludeReservationId = null): bool
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeekIso;

        // 1. Validar contra horarios fijos (excluyendo los que tengan una excepción en esta fecha)
        $fixedConflict = FixedSchedule::where('court_id', $courtId)
            ->where('day_of_week', $dayOfWeek)
            ->whereDoesntHave('exceptions', function ($query) use ($date) {
                $query->where('date', $date);
            })
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($fixedConflict) {
            return false;
        }

        // 2. Validar contra reservas existentes (no canceladas)
        $reservationConflictQuery = Reservation::where('court_id', $courtId)
            ->where('date', $date)
            ->whereIn('status', ['pending', 'verified'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            });

        if ($excludeReservationId) {
            $reservationConflictQuery->where('id', '!=', $excludeReservationId);
        }

        if ($reservationConflictQuery->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Crear una reserva.
     */
    public function create(array $data): Reservation
    {
        return Reservation::create($data);
    }

    /**
     * Buscar una reserva por su ID.
     */
    public function find(int $id): ?Reservation
    {
        return Reservation::find($id);
    }

    /**
     * Buscar una reserva por el número de WhatsApp del cliente en estado pendiente.
     */
    public function findPendingByWhatsapp(string $whatsapp): ?Reservation
    {
        // Limpiar whatsapp por si tiene caracteres especiales
        $cleanWhatsapp = preg_replace('/[^0-9]/', '', $whatsapp);

        return Reservation::where('status', 'pending')
            ->where(function ($query) use ($cleanWhatsapp) {
                $query->where('client_whatsapp', 'like', "%{$cleanWhatsapp}%");
            })
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Obtener reservas pendientes con más de N minutos de antigüedad.
     */
    public function getExpiredPendingReservations(int $minutes): Collection
    {
        $timeThreshold = Carbon::now()->subMinutes($minutes);

        return Reservation::where('status', 'pending')
            ->where('created_at', '<=', $timeThreshold)
            ->get();
    }

    /**
     * Actualizar una reserva.
     */
    public function update(Reservation $reservation, array $data): Reservation
    {
        $reservation->update($data);
        return $reservation;
    }
}
