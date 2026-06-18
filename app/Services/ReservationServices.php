<?php

namespace App\Services;

use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Events\ReservationUpdated;
use App\Models\Reservation;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReservationServices
{
    protected ReservationRepositoryInterface $reservationRepository;

    public function __construct(ReservationRepositoryInterface $reservationRepository)
    {
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * Obtener disponibilidad.
     */
    public function getAvailability(string $date): array
    {
        return $this->reservationRepository->getAvailability($date);
    }

    /**
     * Crear una reserva pendiente.
     */
    public function createReservation(array $data): Reservation
    {
        $isAvailable = $this->reservationRepository->checkAvailability(
            $data['court_id'],
            $data['date'],
            $data['start_time'],
            $data['end_time']
        );

        if (!$isAvailable) {
            throw new Exception("El horario seleccionado no está disponible para esta cancha.");
        }

        $data['status'] = 'pending';
        $reservation = $this->reservationRepository->create($data);

        // Notificar en tiempo real
        broadcast(new ReservationUpdated($reservation))->toOthers();

        // Solo enviar notificaciones automáticas si el usuario está autenticado (Panel Admin)
        if (auth()->check()) {
            // Enviar WhatsApp inicial de confirmación
            $this->sendWhatsAppConfirmationRequest($reservation);

            // Enviar email de confirmación y enlace
            try {
                \Illuminate\Support\Facades\Mail::to('Alexisjsoeva95@gmail.com')
                    ->send(new \App\Mail\ReservationConfirmationMail($reservation));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Error al enviar email de reserva: " . $e->getMessage());
            }
        }

        return $reservation;
    }

    /**
     * Procesar confirmación desde Webhook de WhatsApp.
     */
    public function verifyReservationByWhatsapp(string $whatsapp, string $message): bool
    {
        // Si responde algo que contenga "confirmar" (insensitivo a mayúsculas)
        if (stripos($message, 'confirmar') !== false) {
            $reservation = $this->reservationRepository->findPendingByWhatsapp($whatsapp);

            if ($reservation) {
                $this->reservationRepository->update($reservation, ['status' => 'verified']);

                // Notificar en tiempo real
                broadcast(new ReservationUpdated($reservation))->toOthers();

                // Notificar al cliente la confirmación exitosa
                $this->sendWhatsAppMessage(
                    $reservation->client_whatsapp,
                    "¡Excelente! Tu reserva para la cancha de '{$reservation->court->name}' el día {$reservation->date->format('d/m/Y')} a las {$reservation->start_time} ha sido VERIFICADA exitosamente. ¡Te esperamos!"
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Cancelar una reserva.
     */
    public function cancelReservation(Reservation $reservation): Reservation
    {
        $updated = $this->reservationRepository->update($reservation, ['status' => 'canceled']);

        // Notificar en tiempo real
        broadcast(new ReservationUpdated($updated))->toOthers();

        return $updated;
    }

    /**
     * Enviar mensaje inicial de confirmación a WhatsApp.
     */
    protected function sendWhatsAppConfirmationRequest(Reservation $reservation): void
    {
        $message = "Hola {$reservation->client_name}, has pre-reservado la cancha '{$reservation->court->name}' para el {$reservation->date->format('d/m/Y')} de {$reservation->start_time} a {$reservation->end_time}.\n\nPara confirmar tu reserva, responde con la palabra *CONFIRMAR* en los próximos 15 minutos.";
        
        $this->sendWhatsAppMessage($reservation->client_whatsapp, $message);
    }

    /**
     * Método genérico para enviar WhatsApp mediante API externa (Evolution API / Baileys).
     */
    public function sendWhatsAppMessage(string $whatsapp, string $message): void
    {
        Log::info("Enviando WhatsApp a {$whatsapp}: {$message}");

        try {
            // Ejemplo de llamada a Evolution API u otro servicio externo
            $apiUrl = config('services.whatsapp.api_url');
            $apiKey = config('services.whatsapp.api_key');
            $instance = config('services.whatsapp.instance');

            if ($apiUrl && $apiKey && $instance) {
                Http::withHeaders([
                    'apikey' => $apiKey
                ])->post("{$apiUrl}/message/sendText/{$instance}", [
                    'number' => $whatsapp,
                    'options' => [
                        'delay' => 1200,
                        'presence' => 'composing'
                    ],
                    'textMessage' => [
                        'text' => $message
                    ]
                ]);
            }
        } catch (Exception $e) {
            Log::error("Error al enviar mensaje de WhatsApp: " . $e->getMessage());
        }
    }
}
