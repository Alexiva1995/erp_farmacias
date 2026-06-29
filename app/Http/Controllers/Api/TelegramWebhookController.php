<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Services\TelegramService;
use App\Services\ReservationServices;
use App\Services\GeminiService;
use App\Services\Invoices\InvoiceActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TelegramWebhookController extends Controller
{
    protected TelegramService $telegramService;
    protected ReservationServices $reservationServices;
    protected GeminiService $geminiService;
    protected InvoiceActionService $invoiceActionService;

    public function __construct(
        TelegramService $telegramService,
        ReservationServices $reservationServices,
        GeminiService $geminiService,
        InvoiceActionService $invoiceActionService
    ) {
        $this->telegramService = $telegramService;
        $this->reservationServices = $reservationServices;
        $this->geminiService = $geminiService;
        $this->invoiceActionService = $invoiceActionService;
    }

    /**
     * Manejar las peticiones entrantes de Telegram.
     */
    public function handle(Request $request)
    {
        $update = $request->all();

        // Validar si es un mensaje directo del administrador (texto o foto)
        if (isset($update['message'])) {
            $message = $update['message'];
            $text = $message['text'] ?? '';
            $chatId = $message['chat']['id'] ?? null;
            $fromId = $message['from']['id'] ?? null;

            // Verificar que el mensaje venga del administrador autorizado
            $adminChatId = config('services.telegram.admin_chat_id');
            if ($adminChatId && (string)$fromId === (string)$adminChatId) {

                // 1. Manejo de fotos cuando se espera una factura
                if (isset($message['photo'])) {
                    $stateData = Cache::get('telegram_state_' . $fromId);
                    if ($stateData && is_array($stateData) && $stateData['state'] === 'waiting_for_invoice_photo') {
                        $this->telegramService->sendMessage("⏳ *Procesando imagen de la factura con Inteligencia Artificial (Gemini)...*", $chatId);
                        
                        // Obtener la foto de mayor resolución (último elemento del array)
                        $photo = end($message['photo']);
                        $fileId = $photo['file_id'];
                        
                        $localPath = $this->telegramService->downloadFile($fileId);
                        if (!$localPath) {
                            $this->telegramService->sendMessage("❌ Error al descargar la foto desde Telegram.", $chatId);
                            return response()->json(['status' => 'ok']);
                        }

                        $extractedData = $this->geminiService->analyzeInvoice($localPath);
                        if (file_exists($localPath)) {
                            unlink($localPath);
                        }

                        if (!$extractedData || !isset($extractedData['total_amount'])) {
                            $this->telegramService->sendMessage("❌ No se pudo extraer la información de la factura. Asegúrate de que la imagen sea legible e inténtalo de nuevo con el comando `/registrar_factura`.", $chatId);
                            Cache::forget('telegram_state_' . $fromId);
                            return response()->json(['status' => 'ok']);
                        }

                        // Guardar datos extraídos en caché temporal (10 minutos)
                        Cache::put('telegram_pending_invoice_' . $fromId, $extractedData, 600);

                        // Determinar Proveedor
                        $supplier = null;
                        
                        // Caso A: Se predefinió un proveedor en el comando inicial
                        if (!empty($stateData['supplier_id'])) {
                            $supplier = Supplier::find($stateData['supplier_id']);
                        } 
                        // Caso B: Intentar buscar por RIF o Nombre extraído
                        else {
                            if (!empty($extractedData['supplier_rif'])) {
                                $supplier = Supplier::where('rif', 'like', '%' . $extractedData['supplier_rif'] . '%')->first();
                            }
                            if (!$supplier && !empty($extractedData['supplier_name'])) {
                                $supplier = Supplier::where('name', 'like', '%' . $extractedData['supplier_name'] . '%')->first();
                            }
                        }

                        if ($supplier) {
                            // Proveedor encontrado, proceder a confirmar la factura
                            $extractedData['supplier_id'] = $supplier->id;
                            $extractedData['supplier_name'] = $supplier->name;
                            Cache::put('telegram_pending_invoice_' . $fromId, $extractedData, 600);

                            $msg = "📄 *Factura Detectada con Éxito*\n\n"
                                 . "🏢 *Proveedor:* {$supplier->name}\n"
                                 . "🔢 *Nº Factura:* {$extractedData['invoice_number']}\n"
                                 . "📅 *Fecha Emisión:* {$extractedData['invoice_date']}\n"
                                 . "💰 *Monto Total:* " . number_format($extractedData['total_amount'], 2) . " {$extractedData['currency']}\n\n"
                                 . "¿Deseas registrar esta factura en el sistema?";

                            $replyMarkup = [
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => '✅ Confirmar Registro',
                                            'callback_data' => "confirm_invoice_{$fromId}"
                                        ],
                                        [
                                            'text' => '❌ Cancelar',
                                            'callback_data' => "cancel_invoice_{$fromId}"
                                        ]
                                    ]
                                ]
                            ];

                            $this->telegramService->sendMessage($msg, $chatId, $replyMarkup);
                        } else {
                            // Proveedor no encontrado
                            $nameToUse = $stateData['suggested_supplier_name'] ?? $extractedData['supplier_name'];
                            $rifToUse = $extractedData['supplier_rif'] ?? 'S/R';

                            // Guardar el nombre final en los datos de la factura
                            $extractedData['supplier_name'] = $nameToUse;
                            Cache::put('telegram_pending_invoice_' . $fromId, $extractedData, 600);

                            $msg = "🔍 *Proveedor no Registrado*\n\n"
                                 . "El proveedor *{$nameToUse}* (RIF/NIT: `{$rifToUse}`) no existe en tu sistema.\n\n"
                                 . "¿Deseas crear automáticamente este proveedor y luego registrar la factura?";

                            $replyMarkup = [
                                'inline_keyboard' => [
                                    [
                                        [
                                            'text' => '➕ Crear Proveedor y Seguir',
                                            'callback_data' => "create_supplier_{$fromId}"
                                        ],
                                        [
                                            'text' => '❌ Cancelar',
                                            'callback_data' => "cancel_invoice_{$fromId}"
                                        ]
                                    ]
                                ]
                            ];

                            $this->telegramService->sendMessage($msg, $chatId, $replyMarkup);
                        }
                        return response()->json(['status' => 'ok']);
                    }
                }

                // 2. Analizar si es el comando "registrar factura [proveedor]"
                if (preg_match('/^(?:registrar\s+factura|\/registrar_factura)(?:\s+(.+))?$/i', trim($text), $matches)) {
                    $supplierName = isset($matches[1]) ? trim($matches[1]) : null;

                    if ($supplierName) {
                        $supplier = Supplier::where('name', 'like', "%{$supplierName}%")->first();
                        if ($supplier) {
                            Cache::put('telegram_state_' . $fromId, [
                                'state' => 'waiting_for_invoice_photo',
                                'supplier_id' => $supplier->id,
                                'supplier_name' => $supplier->name
                            ], 300);
                            $this->telegramService->sendMessage("🎯 Asociado al proveedor: *{$supplier->name}*.\n\nPor favor, envía la **foto de la factura** para procesarla.", $chatId);
                        } else {
                            Cache::put('telegram_state_' . $fromId, [
                                'state' => 'waiting_for_invoice_photo',
                                'suggested_supplier_name' => $supplierName
                            ], 300);
                            $this->telegramService->sendMessage("⚠️ El proveedor *{$supplierName}* no existe. Si continúas, te daré la opción de crearlo automáticamente.\n\nPor favor, envía la **foto de la factura**.", $chatId);
                        }
                    } else {
                        Cache::put('telegram_state_' . $fromId, ['state' => 'waiting_for_invoice_photo'], 300);
                        $this->telegramService->sendMessage("📸 Por favor, envía la **foto de la factura** que deseas registrar.", $chatId);
                    }
                    return response()->json(['status' => 'ok']);
                }

                // 3. Comando "cancelar reserva [query]"
                if (preg_match('/^cancelar\s+reserva\s+(.+)$/i', trim($text), $matches)) {
                    $query = trim($matches[1]);
                    $cleanQuery = preg_replace('/[^0-9]/', '', $query);

                    // Buscar reservaciones activas (verified)
                    $reservations = Reservation::with('court')
                        ->where('status', 'with_deposit') // o verified según tu lógica de estados
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
                    return response()->json(['status' => 'ok']);
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
            $fromId = $callbackQuery['from']['id'] ?? null;

            // Confirmar Registro de Factura
            if ($callbackData === "confirm_invoice_{$fromId}") {
                $invoiceData = Cache::get('telegram_pending_invoice_' . $fromId);
                if (!$invoiceData) {
                    $this->answerCallback($callbackQueryId, 'La sesión de la factura ha expirado.');
                    return response()->json(['status' => 'ok']);
                }

                try {
                    // Completar campos requeridos por el servicio de facturas de compra
                    $payload = [
                        'supplier_id' => $invoiceData['supplier_id'],
                        'invoice_number' => $invoiceData['invoice_number'],
                        'control_number' => $invoiceData['control_number'] ?? $invoiceData['invoice_number'],
                        'currency' => $invoiceData['currency'] ?? 'USD',
                        'exp_date' => now()->addDays(30)->toDateString(), // por defecto
                        'received_date' => now()->toDateString(),
                        'created_invoice_date' => $invoiceData['invoice_date'] ?? now()->toDateString(),
                        'exempt_amount' => $invoiceData['exempt_amount'] ?? 0,
                        'taxable_base' => $invoiceData['taxable_base'] ?? 0,
                        'tax_amount' => $invoiceData['tax_amount'] ?? 0,
                        'total_amount' => $invoiceData['total_amount'],
                        'exchange_rate' => 1,
                    ];

                    $invoice = $this->invoiceActionService->createInvoice($payload);

                    $this->answerCallback($callbackQueryId, '✅ Factura registrada con éxito en el ERP.');
                    
                    Http::post("https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/editMessageText", [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => "✅ *[FACTURA REGISTRADA]*\n\n"
                             . "🏢 *Proveedor:* {$invoiceData['supplier_name']}\n"
                             . "🔢 *Factura Nº:* `{$invoiceData['invoice_number']}`\n"
                             . "💰 *Total:* " . number_format($invoiceData['total_amount'], 2) . " {$invoiceData['currency']}\n\n"
                             . "Registrada exitosamente en el sistema.",
                        'parse_mode' => 'Markdown',
                    ]);

                    Cache::forget('telegram_state_' . $fromId);
                    Cache::forget('telegram_pending_invoice_' . $fromId);

                } catch (\Exception $e) {
                    Log::error('[TelegramWebhook] Error al registrar factura: ' . $e->getMessage());
                    $this->answerCallback($callbackQueryId, '❌ Error al guardar la factura: ' . $e->getMessage());
                }
                return response()->json(['status' => 'ok']);
            }

            // Crear Proveedor Inexistente y continuar
            if ($callbackData === "create_supplier_{$fromId}") {
                $invoiceData = Cache::get('telegram_pending_invoice_' . $fromId);
                if (!$invoiceData) {
                    $this->answerCallback($callbackQueryId, 'La sesión ha expirado.');
                    return response()->json(['status' => 'ok']);
                }

                try {
                    // Crear el proveedor
                    $supplier = Supplier::create([
                        'name' => $invoiceData['supplier_name'],
                        'social_reason' => $invoiceData['supplier_name'],
                        'rif' => $invoiceData['supplier_rif'] ?? 'S/R',
                        'type' => \App\Enums\SupplierType::OTHER ?? 'other',
                    ]);

                    // Actualizar los datos de la factura con el nuevo ID del proveedor
                    $invoiceData['supplier_id'] = $supplier->id;
                    Cache::put('telegram_pending_invoice_' . $fromId, $invoiceData, 600);

                    $this->answerCallback($callbackQueryId, "✅ Proveedor '{$supplier->name}' creado.");

                    // Mostrar ahora la confirmación de la factura
                    $msg = "➕ *Proveedor Creado:* `{$supplier->name}`\n\n"
                         . "📄 *Resumen de Factura a Registrar:*\n"
                         . "🏢 *Proveedor:* {$supplier->name}\n"
                         . "🔢 *Nº Factura:* {$invoiceData['invoice_number']}\n"
                         . "📅 *Fecha Emisión:* {$invoiceData['invoice_date']}\n"
                         . "💰 *Monto Total:* " . number_format($invoiceData['total_amount'], 2) . " {$invoiceData['currency']}\n\n"
                         . "¿Deseas proceder con el registro de la factura?";

                    $replyMarkup = [
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => '✅ Confirmar Registro',
                                    'callback_data' => "confirm_invoice_{$fromId}"
                                ],
                                [
                                    'text' => '❌ Cancelar',
                                    'callback_data' => "cancel_invoice_{$fromId}"
                                ]
                            ]
                        ]
                    ];

                    Http::post("https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/editMessageText", [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => $msg,
                        'parse_mode' => 'Markdown',
                        'reply_markup' => json_encode($replyMarkup)
                    ]);

                } catch (\Exception $e) {
                    Log::error('[TelegramWebhook] Error al crear proveedor: ' . $e->getMessage());
                    $this->answerCallback($callbackQueryId, '❌ Error al crear proveedor: ' . $e->getMessage());
                }
                return response()->json(['status' => 'ok']);
            }

            // Cancelar Proceso
            if ($callbackData === "cancel_invoice_{$fromId}") {
                Cache::forget('telegram_state_' . $fromId);
                Cache::forget('telegram_pending_invoice_' . $fromId);

                $this->answerCallback($callbackQueryId, 'Proceso cancelado.');
                Http::post("https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/editMessageText", [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => "❌ *[PROCESO CANCELADO]*\n\nLa factura o la creación del proveedor fue cancelada.",
                    'parse_mode' => 'Markdown',
                ]);
                return response()->json(['status' => 'ok']);
            }

            // Formato de cancelación de reservas: cancel_res_{id}_{token}
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

                    $this->reservationServices->cancelReservation($reservation);
                    $this->answerCallback($callbackQueryId, '✅ Reserva cancelada y cancha liberada con éxito.');
                    $this->updateMessageToCanceled($chatId, $messageId, $reservation);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Responder a la consulta de callback de Telegram.
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
