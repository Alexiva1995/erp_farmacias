<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\TelegramService;
use App\Services\ReservationServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    protected TelegramService $telegramService;
    protected ReservationServices $reservationServices;

    public function __construct(TelegramService $telegramService, ReservationServices $reservationServices)
    {
        $this->telegramService = $telegramService;
        $this->reservationServices = $reservationServices;
    }

    /**
     * Manejar las peticiones entrantes de Telegram.
     */
    public function handle(Request $request)
    {
        $update = $request->all();

        // Validar si es un mensaje de texto directo del administrador
        if (isset($update['message'])) {
            $message = $update['message'];
            $text = $message['text'] ?? '';
            $chatId = $message['chat']['id'] ?? null;
            $fromId = $message['from']['id'] ?? null;

            // Verificar que el mensaje venga del administrador autorizado
            $adminChatId = config('services.telegram.admin_chat_id');
            if ($adminChatId && (string)$fromId === (string)$adminChatId) {
                // Analizar si el mensaje tiene el comando "cancelar reserva [query]"
                if (preg_match('/^cancelar\s+reserva\s+(.+)$/i', trim($text), $matches)) {
                    $query = trim($matches[1]);
                    $cleanQuery = preg_replace('/[^0-9]/', '', $query);

                    // Buscar reservaciones activas (verified)
                    $reservations = Reservation::with('court')
                        ->where('status', 'verified')
                        ->where('date', '>=', now()->toDateString())
                        ->where(function ($q) use ($cleanQuery, $query) {
                            $q->where('identification', 'like', "%{$query}%")
                              ->orWhere('client_whatsapp', 'like', "%{$cleanQuery}%");
                        })
                        ->orderBy('date', 'asc')
                        ->orderBy('start_time', 'asc')
                        ->get();

                    if ($reservations->isEmpty()) {
                        $this->telegramService->sendMessage("🔍 No se encontraron reservas activas para: *{$query}*", $chatId);
                    } else {
                        $this->telegramService->sendMessage("📋 Se encontraron *" . $reservations->count() . "* reservas activas. Haz clic en el botón para confirmar la cancelación:", $chatId);

                        foreach ($reservations as $res) {
                            $todayFormatted = $res->date->format('d/m/Y');
                            $formattedStart = \Carbon\Carbon::parse($res->start_time)->format('g:i A');
                            $formattedEnd   = \Carbon\Carbon::parse($res->end_time)->format('g:i A');
                            $token = sha1($res->id . $res->created_at . config('app.key'));

                            $msg = "🏟️ *Cancha:* {$res->court->name}\n"
                                 . "👤 *Cliente:* {$res->client_name} ({$res->identification})\n"
                                 . "📅 *Fecha:* {$todayFormatted}\n"
                                 . "🕒 *Horario:* {$formattedStart} a {$formattedEnd}\n"
                                 . "📞 *WhatsApp:* {$res->client_whatsapp}";

                            $replyMarkup = [
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => '❌ Confirmar Cancelación',
                                            'callback_data' => "cancel_res_{$res->id}_{$token}"
                                        ]
                                    ]
                                ]
                            ];

                            $this->telegramService->sendMessage($msg, $chatId, $replyMarkup);
                        }
                    }
                }
            }
        }

        // Validar si es una consulta de botón (callback_query)
        if (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];
            $callbackData = $callbackQuery['data'] ?? '';
            $callbackQueryId = $callbackQuery['id'];
            $messageId = $callbackQuery['message']['message_id'] ?? null;
            $chatId = $callbackQuery['message']['chat']['id'] ?? null;

            // Formato esperado: cancel_res_{id}_{token}
            if (str_starts_with($callbackData, 'cancel_res_')) {
                $parts = explode('_', $callbackData);
                if (count($parts) >= 4) {
                    $reservationId = (int)$parts[2];
                    $token = $parts[3];

                    $reservation = Reservation::with('court')->find($reservationId);

                    if (!$reservation) {
                        $this->answerCallback($callbackQueryId, 'La reserva ya no existe.');
                        return response()->json(['status' => 'ok']);
                    }

                    // Validar token de seguridad
                    $expectedToken = sha1($reservation->id . $reservation->created_at . config('app.key'));
                    if ($token !== $expectedToken) {
                        $this->answerCallback($callbackQueryId, 'Acceso denegado. Token inválido.');
                        return response()->json(['status' => 'ok']);
                    }

                    if ($reservation->status === 'canceled') {
                        $this->answerCallback($callbackQueryId, 'Esta reserva ya estaba cancelada.');
                        return response()->json(['status' => 'ok']);
                    }

                    // Cancelar la reserva a través del servicio para disparar la notificación al canal de Telegram
                    $this->reservationServices->cancelReservation($reservation);

                    // Notificar éxito al usuario en Telegram (pop-up)
                    $this->answerCallback($callbackQueryId, '✅ Reserva cancelada y cancha liberada con éxito.');

                    // Actualizar el mensaje original para reflejar la cancelación
                    $this->updateMessageToCanceled($chatId, $messageId, $reservation);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Responder a la consulta de callback de Telegram (para quitar el cargando del botón).
     */
    protected function answerCallback(string $callbackQueryId, string $text): void
    {
        $token = config('services.telegram.bot_token');
        if ($token) {
            Http::post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => true,
            ]);
        }
    }

    /**
     * Editar el mensaje original de Telegram para mostrar que fue cancelada.
     */
    protected function updateMessageToCanceled(int $chatId, int $messageId, Reservation $reservation): void
    {
        $token = config('services.telegram.bot_token');
        if (!$token) return;

        $today = $reservation->date->toDateString();
        $todayFormatted = $reservation->date->format('d/m/Y');
        $carbonDate = \Carbon\Carbon::parse($today);
        $dayOfWeek = $carbonDate->dayOfWeekIso;

        // Obtener agenda actualizada
        $dailyReservations = Reservation::with('court')
            ->whereDate('date', $today)
            ->whereIn('status', ['verified', 'in_progress', 'completed'])
            ->get();

        $dailyFixed = \App\Models\FixedSchedule::with('court')
            ->where('day_of_week', $dayOfWeek)
            ->whereDoesntHave('exceptions', function ($query) use ($today) {
                $query->where('date', $today);
            })
            ->get();

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

        $formattedStart = \Carbon\Carbon::parse($reservation->start_time)->format('g:i A');
        $formattedEnd   = \Carbon\Carbon::parse($reservation->end_time)->format('g:i A');

        $msg = "❌ *[RESERVA CANCELADA Y LIBERADA]* ❌\n\n"
             . "👤 *Cliente:* {$reservation->client_name}\n"
             . "🏟️ *Cancha:* {$reservation->court->name}\n"
             . "📅 *Fecha:* {$todayFormatted}\n"
             . "🕒 *Horario:* {$formattedStart} a {$formattedEnd}\n\n"
             . "📋 *Agenda consolidada actualizada ({$todayFormatted}):*\n";

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

        // Editar el mensaje en Telegram
        Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ]);
    }
}
