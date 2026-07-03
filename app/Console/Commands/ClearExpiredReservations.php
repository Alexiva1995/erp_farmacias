<?php

namespace App\Console\Commands;

use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Events\ReservationUpdated;
use App\Services\ReservationServices;
use Illuminate\Console\Command;

class ClearExpiredReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-expired-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancela reservas pendientes que tienen más de 15 minutos de antigüedad';

    /**
     * Execute the console command.
     */
    public function handle(ReservationRepositoryInterface $reservationRepository, ReservationServices $reservationServices): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('reservations')) {
            $this->info('La tabla de reservas no existe en este tipo de negocio. Saltando...');
            return 0;
        }

        $expiredReservations = $reservationRepository->getExpiredPendingReservations(15);

        if ($expiredReservations->isEmpty()) {
            $this->info('No hay reservas pendientes expiradas.');
            return 0;
        }

        foreach ($expiredReservations as $reservation) {
            $reservationRepository->update($reservation, ['status' => 'canceled']);
            
            // Transmitir evento en tiempo real
            broadcast(new ReservationUpdated($reservation))->toOthers();

            // Notificar al cliente mediante el servicio de WhatsApp
            $reservationServices->sendWhatsAppMessage(
                $reservation->client_whatsapp,
                "Tu reserva de la cancha '{$reservation->court->name}' para el día {$reservation->date->format('d/m/Y')} a las {$reservation->start_time} ha sido CANCELADA debido a que no confirmaste a tiempo (límite de 15 minutos)."
            );

            $this->info("Reserva ID {$reservation->id} de {$reservation->client_name} cancelada por expiración.");
        }

        $this->info('Proceso de liberación de reservas finalizado.');
        return 0;
    }
}
