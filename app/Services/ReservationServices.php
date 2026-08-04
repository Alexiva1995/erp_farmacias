<?php

declare(strict_types=1);

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
    /**
     * Normalizar número de teléfono al formato venezolano (58...)
     */
    protected function normalizeVenezuelanPhone(string $phone): string
    {
        // Remover todo excepto números
        $clean = preg_replace('/[^0-9]/', '', $phone);
        
        // Si empieza por 0, por ejemplo 04121234567, remover el 0 y anteponer 58
        if (str_starts_with($clean, '0') && strlen($clean) === 11) {
            $clean = '58' . substr($clean, 1);
        }
        // Si tiene 10 dígitos y empieza por 4, asumimos que le falta el código de país 58
        elseif (strlen($clean) === 10 && str_starts_with($clean, '4')) {
            $clean = '58' . $clean;
        }
        
        return $clean;
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

        // 1. Normalizar el número de teléfono
        $normalizedPhone = $this->normalizeVenezuelanPhone($data['client_whatsapp']);
        $data['client_whatsapp'] = $normalizedPhone;

        // 2. Gestionar la creación o enlace automático del cliente
        $clientId = $data['client_id'] ?? null;
        $identification = $data['identification'] ?? null;

        if (!$clientId && $identification) {
            // Buscar por cédula en la tabla de clientes
            $existingClient = \App\Models\Client::where('identification', $identification)->first();
            if ($existingClient) {
                $clientId = $existingClient->id;
                // Si el cliente existente no tiene teléfono registrado o difiere, lo actualizamos
                if (empty($existingClient->phone) || $existingClient->phone !== $normalizedPhone) {
                    $existingClient->update(['phone' => $normalizedPhone]);
                }
            } else {
                // Crear un nuevo cliente de manera transparente
                // Separar nombre y apellido si es posible para mantener consistencia
                $nameParts = explode(' ', $data['client_name'], 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';

                $newClient = \App\Models\Client::create([
                    'name' => $firstName,
                    'last_name' => $lastName,
                    'identification_type' => 'V-', // Por defecto Cédula Venezolana
                    'identification' => $identification,
                    'phone' => $normalizedPhone,
                    'client_type' => 'Ocasional',
                ]);
                $clientId = $newClient->id;
            }
        } elseif (!$clientId && !$identification) {
            // Buscar por teléfono si no se envió cédula
            $existingClientByPhone = \App\Models\Client::where('phone', $normalizedPhone)->first();
            if ($existingClientByPhone) {
                $clientId = $existingClientByPhone->id;
            } else {
                // Crear un cliente nuevo con Nombre y Teléfono, generando una identificación temporal
                $nameParts = explode(' ', $data['client_name'], 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';

                // Generar identificación temporal para evitar la restricción NOT NULL y UNIQUE de la BD
                $tempId = 'TEMP-' . preg_replace('/[^0-9]/', '', $normalizedPhone);
                if (\App\Models\Client::where('identification', $tempId)->exists()) {
                    $tempId .= '-' . time();
                }

                $newClient = \App\Models\Client::create([
                    'name' => $firstName,
                    'last_name' => $lastName,
                    'identification_type' => 'V-',
                    'identification' => $tempId,
                    'phone' => $normalizedPhone,
                    'client_type' => 'Ocasional',
                ]);
                $clientId = $newClient->id;
            }
        }

        $data['client_id'] = $clientId;
        $data['status'] = 'verified';
        
        $reservation = $this->reservationRepository->create($data);

        // Asociar la visita actual (IP) para registrar la conversión exitosa
        try {
            $ipAddress = request()->ip();
            $visit = \App\Models\BookingVisit::where('ip_address', $ipAddress)
                ->where('converted', false)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($visit) {
                $visit->update([
                    'converted' => true,
                    'reservation_id' => $reservation->id
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Error al registrar conversión de visita: " . $e->getMessage());
        }

        // Si solicita hora fija semanal, enviar notificación especial de inmediato
        if ($reservation->request_weekly_fixed) {
            try {
                $telegram = resolve(\App\Services\TelegramService::class);
                $weeklyMsg = "🔄 *¡Solicitud de Hora Fija Semanal!* 🔄\n\n"
                           . "El cliente *{$reservation->client_name}* ({$reservation->identification}) "
                           . "solicita que su horario de reserva sea *FIJO SEMANALMENTE*:\n"
                           . "⚽ *Cancha:* {$reservation->court->name}\n"
                           . "📅 *Fecha Solicitud:* {$reservation->date->format('d/m/Y')}\n"
                           . "🕒 *Horario:* " . substr($reservation->start_time, 0, 5) . " a " . substr($reservation->end_time, 0, 5) . "\n"
                           . "📞 *WhatsApp:* {$reservation->client_whatsapp}\n\n"
                           . "⚠️ _Requiere aprobación administrativa en el panel._";
                $telegram->sendMessage($weeklyMsg);
            } catch (\Exception $e) {
                \Log::error("Error al enviar alerta de hora fija a Telegram: " . $e->getMessage());
            }
        }

        // Notificar en tiempo real
        broadcast(new ReservationUpdated($reservation))->toOthers();

        // 1. Enviar notificación inmediata a Telegram del administrador (siempre como confirmada)
        $this->sendTelegramNotification($reservation, 'verified');

        // 2. Si es creada desde el Panel Admin (Autenticado)
        if (auth()->check()) {
            // Enviar email de notificación al administrador
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

                // Notificar a Telegram de la confirmación
                $this->sendTelegramNotification($reservation, 'verified');

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

        // Notificar a Telegram de la cancelación
        $this->sendTelegramNotification($updated, 'canceled');

        return $updated;
    }

    /**
     * Enviar mensaje de notificación consolidada a Telegram.
     */
    public function sendTelegramNotification(Reservation $reservation, string $type = 'new'): void
    {
        try {
            $telegram = resolve(\App\Services\TelegramService::class);
            
            $today = $reservation->date->toDateString();
            $todayFormatted = $reservation->date->format('d/m/Y');
            $carbonDate = \Carbon\Carbon::parse($today);
            $dayOfWeek = $carbonDate->dayOfWeekIso;

            // 1. Reservaciones confirmadas del día
            $dailyReservations = \App\Models\Reservation::with('court')
                ->whereDate('date', $today)
                ->whereIn('status', ['verified', 'in_progress', 'completed'])
                ->get();

            // 2. Horarios fijos del día de la semana (que no tengan excepciones hoy)
            $dailyFixed = \App\Models\FixedSchedule::with('court')
                ->where('day_of_week', $dayOfWeek)
                ->whereDoesntHave('exceptions', function ($query) use ($today) {
                    $query->where('date', $today);
                })
                ->get();

            // Unificar agenda: reservas confirmadas + fijos
            $agendaItems = collect();

            foreach ($dailyReservations as $r) {
                $agendaItems->push([
                    'court_name'  => $r->court->name,
                    'start_time'  => $r->start_time,
                    'end_time'    => $r->end_time,
                    'client_name' => $r->client_name,
                    'is_fixed'    => false,
                ]);
            }

            foreach ($dailyFixed as $f) {
                $agendaItems->push([
                    'court_name'  => $f->court->name,
                    'start_time'  => $f->start_time,
                    'end_time'    => $f->end_time,
                    'client_name' => $f->client_name,
                    'is_fixed'    => true,
                ]);
            }

            // Convertir horas de la reserva a formato 12h AM/PM
            $formattedStart = \Carbon\Carbon::parse($reservation->start_time)->format('g:i A');
            $formattedEnd   = \Carbon\Carbon::parse($reservation->end_time)->format('g:i A');

            // Determinar etiqueta de estado
            $statusText = "⏳ *[PRE-RESERVA PENDIENTE]*";
            if ($reservation->status === 'verified') {
                $statusText = "✅ *[VERIFICADA / CONFIRMADA]*";
            } elseif ($reservation->status === 'canceled') {
                $statusText = "❌ *[CANCELADA]*";
            }

            if ($type === 'verified') {
                $statusText = "✅ *[VERIFICADA / CONFIRMADA]*";
            } elseif ($type === 'canceled') {
                $statusText = "❌ *[CANCELADA]*";
            }

            // Cabecera del mensaje
            $msg = "⚽ *Alerta de Reserva ({$statusText})* ⚽\n\n"
                 . "👤 *Cliente:* {$reservation->client_name} ({$reservation->identification})\n"
                 . "🏟️ *Lugar:* {$reservation->court->name}\n"
                 . "📅 *Fecha:* {$todayFormatted}\n"
                 . "🕒 *Horario:* {$formattedStart} a {$formattedEnd}\n"
                 . "📞 *WhatsApp:* {$reservation->client_whatsapp}\n";

            if ($reservation->request_weekly_fixed) {
                $msg .= "🔄 *[Solicita Horario Fijo Semanal]*\n";
            }

            // Agenda agrupada por cancha
            $msg .= "\n📋 *Agenda consolidada para hoy ({$todayFormatted}):*\n";

            if ($agendaItems->isEmpty()) {
                $msg .= "_Ninguna reserva programada para hoy._";
            } else {
                $grouped = $agendaItems->groupBy('court_name');
                foreach ($grouped as $courtName => $items) {
                    $msg .= "\n*{$courtName}*\n";
                    $sortedItems = $items->sortBy('start_time')->values();
                    foreach ($sortedItems as $item) {
                        $rStart   = \Carbon\Carbon::parse($item['start_time'])->format('g:i A');
                        $rEnd     = \Carbon\Carbon::parse($item['end_time'])->format('g:i A');
                        $fixedTag = $item['is_fixed'] ? ' 🔄' : '';
                        $msg .= "{$rStart} a {$rEnd} - {$item['client_name']}{$fixedTag}\n";
                    }
                }
            }

            $telegram->sendMessage($msg);
        } catch (\Exception $e) {
            \Log::error("Error al enviar notificación a Telegram: " . $e->getMessage());
        }
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
