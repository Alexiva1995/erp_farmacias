<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Reservation $reservation;

    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    /**
     * Construir el mensaje.
     */
    public function build(): self
    {
        $phone = $this->reservation->client_whatsapp;
        $courtName = $this->reservation->court->name;
        $dateFormatted = $this->reservation->date->format('d/m/Y');
        $timeRange = "{$this->reservation->start_time} - {$this->reservation->end_time}";

        // Mensaje predeterminado para enviar al cliente por WhatsApp
        $whatsappText = urlencode("Hola {$this->reservation->client_name}, confirmo tu reserva para la cancha '{$courtName}' el día {$dateFormatted} a las {$this->reservation->start_time}. ¡Te esperamos!");
        $whatsappUrl = "https://wa.me/{$phone}?text={$whatsappText}";

        // Enlace de confirmación directa en el backend
        $directConfirmUrl = url("/api/public/reservations/confirm-direct/{$this->reservation->id}");

        return $this->subject("Nueva Reserva Pendiente: {$courtName}")
                    ->html("
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                            <h2 style='color: #E20074; text-align: center;'>⚽ Nueva Reserva Pendiente</h2>
                            <p>Hola Administrador,</p>
                            <p>Se ha registrado una nueva pre-reserva en el sistema:</p>
                            
                            <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                                <tr>
                                    <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #f0f0f0;'>Cliente:</td>
                                    <td style='padding: 8px; border-bottom: 1px solid #f0f0f0;'>{$this->reservation->client_name}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #f0f0f0;'>WhatsApp:</td>
                                    <td style='padding: 8px; border-bottom: 1px solid #f0f0f0;'>{$phone}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #f0f0f0;'>Cancha:</td>
                                    <td style='padding: 8px; border-bottom: 1px solid #f0f0f0;'>{$courtName}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #f0f0f0;'>Fecha y Hora:</td>
                                    <td style='padding: 8px; border-bottom: 1px solid #f0f0f0;'>{$dateFormatted} de {$timeRange}</td>
                                </tr>
                            </table>

                            <div style='text-align: center; margin-top: 30px;'>
                                <!-- Botón para chatear con cliente en WhatsApp -->
                                <a href='{$whatsappUrl}' target='_blank' style='background-color: #25D366; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; margin-right: 10px;'>
                                    💬 Contactar por WhatsApp
                                </a>

                                <!-- Botón para confirmar directamente en el sistema -->
                                <a href='{$directConfirmUrl}' style='background-color: #E20074; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                                    ✅ Confirmar Reserva
                                </a>
                            </div>
                            
                            <p style='font-size: 12px; color: #999; margin-top: 30px; text-align: center;'>
                                Complejo Deportivo Gol Club
                            </p>
                        </div>
                    ");
    }
}
