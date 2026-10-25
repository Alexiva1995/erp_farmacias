<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Reservation;

interface DiningReservationServiceInterface
{
    public function listReservations(array $filters = []): array;

    public function createReservation(array $data): Reservation;

    public function checkIn(Reservation $reservation, int $sellerId): Order;

    public function cancelReservation(Reservation $reservation): bool;
}
