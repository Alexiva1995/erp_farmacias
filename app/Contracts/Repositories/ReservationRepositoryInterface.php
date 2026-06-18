<?php

namespace App\Contracts\Repositories;

use App\Models\Reservation;
use Illuminate\Support\Collection;

interface ReservationRepositoryInterface
{
    /**
     * Obtener todas las canchas.
     *
     * @return Collection
     */
    public function getAllCourts(): Collection;

    /**
     * Obtener reservas y horarios fijos cruzados para una fecha específica.
     *
     * @param string $date
     * @return array
     */
    public function getAvailability(string $date): array;

    /**
     * Verificar si un bloque de horario está disponible para una cancha en una fecha.
     *
     * @param int $courtId
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @param int|null $excludeReservationId
     * @return bool
     */
    public function checkAvailability(int $courtId, string $date, string $startTime, string $endTime, ?int $excludeReservationId = null): bool;

    /**
     * Crear una reserva.
     *
     * @param array $data
     * @return Reservation
     */
    public function create(array $data): Reservation;

    /**
     * Buscar una reserva por su ID.
     *
     * @param int $id
     * @return Reservation|null
     */
    public function find(int $id): ?Reservation;

    /**
     * Buscar una reserva por el número de WhatsApp del cliente en estado pendiente.
     *
     * @param string $whatsapp
     * @return Reservation|null
     */
    public function findPendingByWhatsapp(string $whatsapp): ?Reservation;

    /**
     * Obtener reservas pendientes con más de N minutos de antigüedad.
     *
     * @param int $minutes
     * @return Collection
     */
    public function getExpiredPendingReservations(int $minutes): Collection;

    /**
     * Actualizar una reserva.
     *
     * @param Reservation $reservation
     * @param array $data
     * @return Reservation
     */
    public function update(Reservation $reservation, array $data): Reservation;
}
