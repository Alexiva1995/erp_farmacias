<?php

namespace App\Mail;

use App\Models\FixedSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FixedScheduleMail extends Mailable
{
    use Queueable, SerializesModels;

    public FixedSchedule $fixedSchedule;

    public function __construct(FixedSchedule $fixedSchedule)
    {
        $this->fixedSchedule = $fixedSchedule;
    }

    /**
     * Construir el mensaje.
     */
    public function build(): self
    {
        $courtName = $this->fixedSchedule->court->name;
        $days = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        ];
        $dayName = $days[$this->fixedSchedule->day_of_week] ?? '';
        $timeRange = "{$this->fixedSchedule->start_time} - {$this->fixedSchedule->end_time}";

        return $this->subject("Horario Fijo Configurado: {$courtName}")
                    ->html("
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                            <h2 style='color: #007A99; text-align: center;'>🗓️ Horario Fijo Configurado</h2>
                            <p>Hola Administrador,</p>
                            <p>Se ha configurado un nuevo horario fijo en el sistema:</p>
                            
                            <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                                <tr>
                                    <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #f0f0f0;'>Cliente:</td>
                                    <td style='padding: 8px; border-bottom: 1px solid #f0f0f0;'>{$this->fixedSchedule->client_name}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #f0f0f0;'>WhatsApp:</td>
                                    <td style='padding: 8px; border-bottom: 1px solid #f0f0f0;'>{$this->fixedSchedule->client_whatsapp}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #f0f0f0;'>Cancha:</td>
                                    <td style='padding: 8px; border-bottom: 1px solid #f0f0f0;'>{$courtName}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #f0f0f0;'>Día de la semana:</td>
                                    <td style='padding: 8px; border-bottom: 1px solid #f0f0f0;'>{$dayName}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px; font-weight: bold; border-bottom: 1px solid #f0f0f0;'>Horario:</td>
                                    <td style='padding: 8px; border-bottom: 1px solid #f0f0f0;'>{$timeRange}</td>
                                </tr>
                            </table>

                            <p style='text-align: center; color: #666; font-size: 14px; margin-top: 30px;'>
                                Este bloque de horario estará reservado automáticamente todas las semanas para este cliente.
                            </p>
                            
                            <p style='font-size: 12px; color: #999; margin-top: 30px; text-align: center;'>
                                Complejo Deportivo Gol Club
                            </p>
                        </div>
                    ");
    }
}
