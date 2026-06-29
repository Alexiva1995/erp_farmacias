<?php

namespace App\Services\Telegram;

use App\Models\Reservation;
use App\Models\Supplier;
use App\Services\TelegramService;
use App\Services\ReservationServices;
use App\Services\GeminiService;
use App\Services\Invoices\InvoiceActionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TelegramWebhookService
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
     * Procesar la petición entrante del Webhook de Telegram.
     */
    public function handleWebhook(array $update): void
    {
        // 1. Validar si es un mensaje directo del administrador (texto o foto)
        if (isset($update['message'])) {
            $this->handleIncomingMessage($update['message']);
        }

        // 2. Validar si es una consulta de botón (callback_query)
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
    }

    /**
     * Procesar mensajes entrantes (texto o fotos).
     */
    protected function handleIncomingMessage(array $message): void
    {
        $text = $message['text'] ?? '';
        $chatId = $message['chat']['id'] ?? null;
        $fromId = $message['from']['id'] ?? null;

        // Verificar que el mensaje venga del administrador autorizado
        $adminChatId = config('services.telegram.admin_chat_id');
        if (!$adminChatId || (string)$fromId !== (string)$adminChatId) {
            return;
        }

        // Permite al usuario abortar cualquier flujo activo (ej: registro de facturas) escribiendo 'cancelar'
        $cleanText = strtolower(trim($text));
        if ($cleanText === 'cancelar' || $cleanText === '/cancelar') {
            Cache::forget('telegram_state_' . $fromId);
            Cache::forget('telegram_pending_invoice_' . $fromId);
            $this->telegramService->sendMessage("❌ *[PROCESO CANCELADO]*\n\nSe ha cancelado el registro de la factura actual y limpiado tu estado.", $chatId);
            return;
        }

        // 1. Verificar si el usuario está en un estado conversacional esperando un dato específico
        $stateData = Cache::get('telegram_state_' . $fromId);
        if ($stateData && is_array($stateData)) {
            if ($stateData['state'] === 'waiting_for_invoice_total') {
                $this->processUserProvidedTotal(trim($text), $fromId, $chatId, $stateData);
                return;
            }
            if ($stateData['state'] === 'waiting_for_invoice_supplier_name') {
                $this->processUserProvidedSupplierName(trim($text), $fromId, $chatId, $stateData);
                return;
            }
            if ($stateData['state'] === 'waiting_for_products_list') {
                $this->processProductsList(trim($text), $fromId, $chatId);
                return;
            }
            if ($stateData['state'] === 'waiting_for_fast_fruit_invoice') {
                $this->processFastFruitInvoice(trim($text), $fromId, $chatId);
                return;
            }
        }

        // Caso A: Se recibe una foto y se está esperando una factura
        if (isset($message['photo'])) {
            $this->processInvoicePhoto($message['photo'], $fromId, $chatId);
            return;
        }

        // Caso B: Comando para iniciar el registro de facturas
        // Formatos aceptados:
        // registrar factura [informal] [COP|USD|Bs] [Nombre Proveedor]
        if (preg_match('/^(?:registrar\s+factura|\/registrar_factura)(?:\s+(informal))?(?:\s+(COP|USD|Bs))?(?:\s+(.+))?$/i', trim($text), $matches)) {
            $isInformal = !empty($matches[1]);
            $forcedCurrency = !empty($matches[2]) ? $matches[2] : null;
            $supplierName = !empty($matches[3]) ? trim($matches[3]) : null;

            $this->initInvoiceRegistration($supplierName, $isInformal, $forcedCurrency, $fromId, $chatId);
            return;
        }

        // Caso C: Comando para cancelar reservas
        if (preg_match('/^cancelar\s+reserva\s+(.+)$/i', trim($text), $matches)) {
            $this->processReservationCancellation(trim($matches[1]), $chatId);
            return;
        }

        // Caso D: Comando para registrar productos rápidamente
        if (preg_match('/^(?:registrar\s+productos?|\/registrar_productos?)(?:\s+(.+))?$/i', trim($text), $matches)) {
            $productsList = isset($matches[1]) ? trim($matches[1]) : null;
            if ($productsList) {
                $this->processProductsList($productsList, $fromId, $chatId);
            } else {
                Cache::put('telegram_state_' . $fromId, ['state' => 'waiting_for_products_list'], 300);
                $this->telegramService->sendMessage("📋 Envíame la lista de productos que deseas registrar en la base de datos (escribe un nombre por línea o sepáralos por comas):", $chatId);
            }
            return;
        }

        // Caso E: Comando para registro ultra rápido de facturas de frutas
        if (preg_match('/^(?:registrar\s+frutas?|\/registrar_frutas?)(?:\s+(.+))?$/i', trim($text), $matches)) {
            $fruitsText = isset($matches[1]) ? trim($matches[1]) : null;
            if ($fruitsText) {
                $this->processFastFruitInvoice($fruitsText, $fromId, $chatId);
            } else {
                Cache::put('telegram_state_' . $fromId, ['state' => 'waiting_for_fast_fruit_invoice'], 300);
                $this->telegramService->sendMessage("🍎 *[REGISTRO RÁPIDO DE FRUTAS]*\n\nEnvíame la lista de frutas con sus cantidades y precios.\n\n*Ejemplo:* `Fresa 2000g 18.000 COP - Cambur 1000 2.000 COP - kiwi 320 1560 COP`", $chatId);
            }
            return;
        }
    }

    /**
     * Inicializar el flujo de registro de facturas.
     */
    protected function initInvoiceRegistration(?string $supplierName, bool $isInformal, ?string $forcedCurrency, $fromId, $chatId): void
    {
        $state = [
            'state' => 'waiting_for_invoice_photo',
            'is_informal' => $isInformal,
            'forced_currency' => $forcedCurrency
        ];

        $info = ($isInformal ? "Informal 📝 " : "") . ($forcedCurrency ? "({$forcedCurrency}) " : "");

        if ($supplierName) {
            $supplier = Supplier::where('name', 'like', "%{$supplierName}%")->first();
            if ($supplier) {
                $state['supplier_id'] = $supplier->id;
                $state['supplier_name'] = $supplier->name;
                Cache::put('telegram_state_' . $fromId, $state, 300);
                $this->telegramService->sendMessage("🎯 Asociado al proveedor: *{$supplier->name}* {$info}.\n\nPor favor, envía la **foto de la factura** para procesarla.", $chatId);
            } else {
                $state['suggested_supplier_name'] = $supplierName;
                Cache::put('telegram_state_' . $fromId, $state, 300);
                $this->telegramService->sendMessage("⚠️ El proveedor *{$supplierName}* no existe {$info}. Si continúas, te daré la opción de crearlo automáticamente.\n\nPor favor, envía la **foto de la factura**.", $chatId);
            }
        } else {
            Cache::put('telegram_state_' . $fromId, $state, 300);
            $this->telegramService->sendMessage("📸 Por favor, envía la **foto de la factura** {$info}que deseas registrar.", $chatId);
        }
    }

    /**
     * Procesar la foto de la factura mediante la IA (Gemini).
     */
    protected function processInvoicePhoto(array $photos, $fromId, $chatId): void
    {
        $stateData = Cache::get('telegram_state_' . $fromId);
        if (!$stateData || !is_array($stateData) || $stateData['state'] !== 'waiting_for_invoice_photo') {
            return;
        }

        $this->telegramService->sendMessage("⏳ *Procesando imagen de la factura con Inteligencia Artificial (Gemini)...*", $chatId);
        
        $photo = end($photos);
        $fileId = $photo['file_id'];
        
        $localPath = $this->telegramService->downloadFile($fileId);
        if (!$localPath) {
            $this->telegramService->sendMessage("❌ Error al descargar la foto desde Telegram.", $chatId);
            return;
        }

        $extractedData = $this->geminiService->analyzeInvoice($localPath);
        if (file_exists($localPath)) {
            unlink($localPath);
        }

        if (!$extractedData) {
            $extractedData = [];
        }

        // Forzar valores por defecto para que no falle por campos vacíos
        if (empty($extractedData['invoice_number'])) {
            $extractedData['invoice_number'] = 'F-' . date('YmdHis');
        }
        if (empty($extractedData['invoice_date'])) {
            $extractedData['invoice_date'] = date('Y-m-d');
        }

        // Forzar moneda si se indicó en el comando
        if (!empty($stateData['forced_currency'])) {
            $extractedData['currency'] = $stateData['forced_currency'];
        }

        // 1. Si falta el nombre del proveedor y no fue predefinido en el comando
        if (empty($extractedData['supplier_name']) && empty($stateData['supplier_name']) && empty($stateData['suggested_supplier_name'])) {
            Cache::put('telegram_state_' . $fromId, [
                'state' => 'waiting_for_invoice_supplier_name',
                'pending_data' => $extractedData,
                'original_state' => $stateData
            ], 300);
            $this->telegramService->sendMessage("⚠️ No logré detectar el nombre del proveedor en la factura. Por favor, escribe el **Nombre del Proveedor**:", $chatId);
            return;
        }

        // 2. Si falta el monto total de la factura
        if (empty($extractedData['total_amount']) || $extractedData['total_amount'] <= 0) {
            Cache::put('telegram_state_' . $fromId, [
                'state' => 'waiting_for_invoice_total',
                'pending_data' => $extractedData,
                'original_state' => $stateData
            ], 300);
            $this->telegramService->sendMessage("💰 No logré detectar el Monto Total en la factura. Por favor, escribe el **Monto Total** (solo números, ej: `150000`):", $chatId);
            return;
        }

        // Evaluar proveedor y mostrar resumen de confirmación
        $this->evaluateSupplierAndShowSummary($extractedData, $stateData, $fromId, $chatId);
    }

    /**
     * Procesar la respuesta del usuario para el Monto Total.
     */
    protected function processUserProvidedTotal(string $text, $fromId, $chatId, array $stateData): void
    {
        $cleanNumber = str_replace([',', ' '], ['', ''], $text);
        if (!is_numeric($cleanNumber)) {
            $this->telegramService->sendMessage("⚠️ Por favor, ingresa un número válido para el total (ej: `150000` o `85.50`):", $chatId);
            return;
        }

        $totalAmount = (float) $cleanNumber;
        $extractedData = $stateData['pending_data'] ?? [];
        $extractedData['total_amount'] = $totalAmount;

        $originalState = $stateData['original_state'] ?? [];

        // Evaluar proveedor y mostrar resumen
        $this->evaluateSupplierAndShowSummary($extractedData, $originalState, $fromId, $chatId);
    }

    /**
     * Procesar la respuesta del usuario para el Nombre del Proveedor.
     */
    protected function processUserProvidedSupplierName(string $text, $fromId, $chatId, array $stateData): void
    {
        if (empty($text)) {
            $this->telegramService->sendMessage("⚠️ Por favor, ingresa un nombre de proveedor válido:", $chatId);
            return;
        }

        $extractedData = $stateData['pending_data'] ?? [];
        $extractedData['supplier_name'] = $text;

        $originalState = $stateData['original_state'] ?? [];

        // Si todavía falta el total, preguntarlo ahora
        if (empty($extractedData['total_amount']) || $extractedData['total_amount'] <= 0) {
            Cache::put('telegram_state_' . $fromId, [
                'state' => 'waiting_for_invoice_total',
                'pending_data' => $extractedData,
                'original_state' => $originalState
            ], 300);
            $this->telegramService->sendMessage("💰 Nombre del proveedor guardado. Ahora, por favor ingresa el **Monto Total** de la factura:", $chatId);
            return;
        }

        // Evaluar proveedor y mostrar resumen
        $this->evaluateSupplierAndShowSummary($extractedData, $originalState, $fromId, $chatId);
    }

    /**
     * Evaluar la existencia del proveedor y mostrar el resumen final interactivo.
     */
    protected function evaluateSupplierAndShowSummary(array $extractedData, array $stateData, $fromId, $chatId): void
    {
        Cache::put('telegram_pending_invoice_' . $fromId, $extractedData, 600);

        $supplier = null;
        if (!empty($stateData['supplier_id'])) {
            $supplier = Supplier::find($stateData['supplier_id']);
        } else {
            $rif = $extractedData['supplier_rif'] ?? '';
            $name = $stateData['suggested_supplier_name'] ?? ($extractedData['supplier_name'] ?? '');

            if (!empty($rif)) {
                $supplier = Supplier::where('rif', 'like', '%' . $rif . '%')->first();
            }
            if (!$supplier && !empty($name)) {
                $supplier = Supplier::where('name', 'like', '%' . $name . '%')->first();
            }
        }

        $typeInfo = !empty($stateData['is_informal']) ? " [Informal 📝]" : "";

        // Guardar el estado final corregido para asegurar que la confirmación tenga los flags
        Cache::put('telegram_state_' . $fromId, $stateData, 600);

        if ($supplier) {
            $extractedData['supplier_id'] = $supplier->id;
            $extractedData['supplier_name'] = $supplier->name;
            Cache::put('telegram_pending_invoice_' . $fromId, $extractedData, 600);

            $msg = "📄 *Factura Detectada con Éxito*{$typeInfo}\n\n"
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
            $nameToUse = $stateData['suggested_supplier_name'] ?? ($extractedData['supplier_name'] ?? 'Proveedor Desconocido');
            $rifToUse = $extractedData['supplier_rif'] ?? 'S/R';

            $extractedData['supplier_name'] = $nameToUse;
            Cache::put('telegram_pending_invoice_' . $fromId, $extractedData, 600);

            $msg = "🔍 *Proveedor no Registrado*{$typeInfo}\n\n"
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
    }

    /**
     * Procesar la cancelación de reservas.
     */
    protected function processReservationCancellation(string $query, $chatId): void
    {
        $cleanQuery = preg_replace('/[^0-9]/', '', $query);

        $reservations = Reservation::with('court')
            ->where('status', 'with_deposit')
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

    /**
     * Manejar las acciones interactivas de botones (Callbacks).
     */
    protected function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'];
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $fromId = $callbackQuery['from']['id'] ?? null;

        // 1. Confirmar Registro de Factura
        if ($callbackData === "confirm_invoice_{$fromId}") {
            $invoiceData = Cache::get('telegram_pending_invoice_' . $fromId);
            $stateData = Cache::get('telegram_state_' . $fromId);
            if (!$invoiceData) {
                $this->answerCallback($callbackQueryId, 'La sesión de la factura ha expirado.');
                return;
            }

            try {
                $isInformal = !empty($stateData['is_informal']);
                
                // Obtener el primer usuario disponible en el sistema para asociarlo al registro automático de la factura
                $adminId = \App\Models\User::first()?->id ?? 1;

                $currency = $invoiceData['currency'] ?? 'USD';
                $exchangeRate = 1;
                if ($currency !== 'USD') {
                    $exchangeRate = app(\App\Services\Resources\ResourceService::class)->getExchangeRate($currency) ?? 1;
                }

                $expDate = now()->addDays(30)->toDateString();

                $payload = [
                    'supplier_id' => $invoiceData['supplier_id'],
                    'invoice_number' => $invoiceData['invoice_number'],
                    'control_number' => $invoiceData['control_number'] ?? $invoiceData['invoice_number'],
                    'currency' => $currency,
                    'exp_date' => $expDate,
                    'payment_date' => $expDate,
                    'received_date' => now()->toDateString(),
                    'created_invoice_date' => $invoiceData['invoice_date'] ?? now()->toDateString(),
                    'exempt_amount' => $isInformal ? $invoiceData['total_amount'] : ($invoiceData['exempt_amount'] ?? 0),
                    'taxable_base' => $isInformal ? 0 : ($invoiceData['taxable_base'] ?? 0),
                    'tax_amount' => $isInformal ? 0 : ($invoiceData['tax_amount'] ?? 0),
                    'total_amount' => $invoiceData['total_amount'],
                    'exchange_rate' => $exchangeRate,
                    'registered_by' => $adminId,
                    'uploaded_by' => $adminId,
                ];

                // Autenticar temporalmente al usuario en la sesión del request para el servicio y observadores
                \Illuminate\Support\Facades\Auth::loginUsingId($adminId);

                $this->invoiceActionService->createInvoice($payload);
                $this->answerCallback($callbackQueryId, '✅ Factura registrada con éxito en el ERP.');
                
                $typeInfo = $isInformal ? " [Informal 📝]" : "";
                
                Http::post("https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/editMessageText", [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => "✅ *[FACTURA REGISTRADA]*{$typeInfo}\n\n"
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
            return;
        }

        // 2. Crear Proveedor Creado en Caliente
        if ($callbackData === "create_supplier_{$fromId}") {
            $invoiceData = Cache::get('telegram_pending_invoice_' . $fromId);
            $stateData = Cache::get('telegram_state_' . $fromId);
            if (!$invoiceData) {
                $this->answerCallback($callbackQueryId, 'La sesión ha expirado.');
                return;
            }

            try {
                $supplier = Supplier::create([
                    'name' => $invoiceData['supplier_name'],
                    'social_reason' => $invoiceData['supplier_name'],
                    'rif' => $invoiceData['supplier_rif'] ?? 'S/R',
                    'type' => \App\Enums\SupplierType::EXTERNO,
                    'dispatch_days' => [],
                    'order_days' => [],
                ]);

                $invoiceData['supplier_id'] = $supplier->id;
                Cache::put('telegram_pending_invoice_' . $fromId, $invoiceData, 600);

                $this->answerCallback($callbackQueryId, "✅ Proveedor '{$supplier->name}' creado.");

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
            return;
        }

        // 3. Cancelar todo el proceso
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
            return;
        }

        // 4. Cancelación de Reservaciones (Gol Club)
        if (str_starts_with($callbackData, 'cancel_res_')) {
            $parts = explode('_', $callbackData);
            if (count($parts) >= 4) {
                $reservationId = (int)$parts[2];
                $token = $parts[3];

                $reservation = Reservation::with('court')->find($reservationId);

                if (!$reservation) {
                    $this->answerCallback($callbackQueryId, 'La reserva ya no existe.');
                    return;
                }

                $expectedToken = sha1($reservation->id . $reservation->created_at . config('app.key'));
                if ($token !== $expectedToken) {
                    $this->answerCallback($callbackQueryId, 'Acceso denegado. Token inválido.');
                    return;
                }

                if ($reservation->status === 'canceled') {
                    $this->answerCallback($callbackQueryId, 'Esta reserva ya estaba cancelada.');
                    return;
                }

                $this->reservationServices->cancelReservation($reservation);
                $this->answerCallback($callbackQueryId, '✅ Reserva cancelada y cancha liberada con éxito.');
                $this->updateMessageToCanceled($chatId, $messageId, $reservation);
            }
        }
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
     * Editar el mensaje de reservación original para marcarlo como cancelado.
     */
    protected function updateMessageToCanceled(int $chatId, int $messageId, Reservation $reservation): void
    {
        $token = config('services.telegram.bot_token');
        if (!$token) return;

        $today = $reservation->date->toDateString();
        $todayFormatted = $reservation->date->format('d/m/Y');
        $carbonDate = \Carbon\Carbon::parse($today);
        $dayOfWeek = $carbonDate->dayOfWeekIso;

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

        Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Procesar y registrar una lista de productos en la base de datos.
     */
    protected function processProductsList(string $text, $fromId, $chatId): void
    {
        if (empty($text)) {
            $this->telegramService->sendMessage("⚠️ La lista de productos está vacía. Inténtalo de nuevo o escribe `cancelar`.", $chatId);
            return;
        }

        // Separar por salto de línea o por comas
        $lines = preg_split('/[\n,]+/', $text);
        $created = [];
        $skipped = [];

        foreach ($lines as $line) {
            $name = trim($line);
            if (empty($name)) {
                continue;
            }

            // Verificar si ya existe
            $exists = \App\Models\Product::where('name', 'like', $name)->exists();
            if ($exists) {
                $skipped[] = $name;
                continue;
            }

            // Crear el producto con valores mínimos
            \App\Models\Product::create([
                'name' => $name,
                'unit_cost' => 0,
                'sale_price' => 0,
                'presentation' => 1,
                'unit_of_measure' => 'und',
                'stock' => 0,
            ]);

            $created[] = $name;
        }

        // Limpiar el estado
        Cache::forget('telegram_state_' . $fromId);

        // Armar mensaje de respuesta
        $msg = "🏁 *[REGISTRO DE PRODUCTOS COMPLETADO]*\n\n";
        
        if (!empty($created)) {
            $msg .= "✅ *Creados con éxito (" . count($created) . "):*\n";
            foreach ($created as $p) {
                $msg .= "• {$p}\n";
            }
            $msg .= "\n";
        }

        if (!empty($skipped)) {
            $msg .= "⚠️ *Omitidos porque ya existían (" . count($skipped) . "):*\n";
            foreach ($skipped as $p) {
                $msg .= "• {$p}\n";
            }
        }

        if (empty($created) && empty($skipped)) {
            $msg .= "❌ No se pudo procesar ningún nombre válido de la lista.";
        }

        $this->telegramService->sendMessage($msg, $chatId);
    }

    /**
     * Procesar y registrar una factura rápida de frutas desde el bot de Telegram.
     */
    protected function processFastFruitInvoice(string $text, $fromId, $chatId): void
    {
        if (empty($text)) {
            $this->telegramService->sendMessage("⚠️ La lista de frutas está vacía. Inténtalo de nuevo o escribe `cancelar`.", $chatId);
            return;
        }

        // 1. Buscar o crear el proveedor "Frutas"
        $supplier = \App\Models\Supplier::firstOrCreate(
            ['name' => 'Frutas'],
            [
                'social_reason' => 'Frutas',
                'rif' => 'V-FRUTAS-01',
                'type' => \App\Enums\SupplierType::EXTERNO,
                'dispatch_days' => [],
                'order_days' => [],
            ]
        );

        // Separar por salto de línea o por guiones " - "
        $itemsRaw = preg_split('/[\n\-]+/', $text);
        $parsedItems = [];
        $currency = 'COP'; // Valor por defecto

        foreach ($itemsRaw as $itemRaw) {
            $itemRaw = trim($itemRaw);
            if (empty($itemRaw)) {
                continue;
            }

            // Expresión regular para casar: [Nombre] [Cantidad] [Monto] [Moneda]
            // Ejemplo: Fresa 2000g 18.000 COP  o  kiwi 320 1560
            if (preg_match('/^([a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+)\s+(\d+)(?:g|gr|kg|und)?\s+([\d\.,]+)(?:\s*(COP|USD|Bs))?/i', $itemRaw, $matches)) {
                $productName = trim($matches[1]);
                $quantity = (float) $matches[2];
                $priceStr = trim($matches[3]);
                $itemCurrency = !empty($matches[4]) ? strtoupper($matches[4]) : null;

                if ($itemCurrency) {
                    $currency = $itemCurrency;
                }

                // Limpiar el separador de miles del precio
                if (strpos($priceStr, '.') !== false && strpos($priceStr, ',') !== false) {
                    $priceStr = str_replace('.', '', $priceStr);
                    $priceStr = str_replace(',', '.', $priceStr);
                } else if (strpos($priceStr, ',') !== false) {
                    if (preg_match('/,\d{3}$/', $priceStr)) {
                        $priceStr = str_replace(',', '', $priceStr);
                    } else {
                        $priceStr = str_replace(',', '.', $priceStr);
                    }
                } else if (strpos($priceStr, '.') !== false) {
                    if (preg_match('/\.\d{3}$/', $priceStr)) {
                        $priceStr = str_replace('.', '', $priceStr);
                    }
                }
                $totalCost = (float) $priceStr;
                $unitCost = $quantity > 0 ? $totalCost / $quantity : 0;

                // Buscar o crear el producto en la base de datos
                $product = \App\Models\Product::firstOrCreate(
                    ['name' => $productName],
                    [
                        'unit_cost' => 0,
                        'sale_price' => 0,
                        'presentation' => 1,
                        'unit_of_measure' => 'und',
                        'stock' => 0,
                    ]
                );

                // Generar número de lote automático correlativo
                $lotCount = $product->lots()->count() + 1;
                $lotNumber = 'L-' . date('Ymd') . '-' . $lotCount;
                $expirationDate = now()->addDays(7)->toDateString();

                $parsedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'total_cost' => $totalCost,
                    'unit_cost' => $unitCost,
                    'lot_number' => $lotNumber,
                    'expiration_date' => $expirationDate,
                ];
            }
        }

        if (empty($parsedItems)) {
            $this->telegramService->sendMessage("❌ No se pudo procesar ningún producto válido. Asegúrate de usar el formato correcto:\n`Fresa 2000g 18.000 COP - Cambur 1000 2.000 COP`", $chatId);
            return;
        }

        // 2. Calcular total de la factura
        $totalAmount = array_sum(array_column($parsedItems, 'total_cost'));

        // Obtener tasa de cambio
        $exchangeRate = 1;
        if ($currency !== 'USD') {
            $exchangeRate = app(\App\Services\Resources\ResourceService::class)->getExchangeRate($currency) ?? 1;
        }

        // Obtener usuario administrador para el registro de auditoría
        $adminId = \App\Models\User::first()?->id ?? 1;
        $invoiceNumber = 'FRUTA-' . date('YmdHis');
        $expDate = now()->addDays(7)->toDateString();

        try {
            // Autenticar temporalmente para evitar que falle en observadores o servicios
            \Illuminate\Support\Facades\Auth::loginUsingId($adminId);

            // Crear cabecera de la factura en el ERP en estado pendiente
            $invoice = \App\Models\Invoice::create([
                'supplier_id' => $supplier->id,
                'invoice_number' => $invoiceNumber,
                'control_number' => $invoiceNumber,
                'currency' => $currency,
                'exp_date' => $expDate,
                'payment_date' => $expDate,
                'received_date' => now()->toDateString(),
                'created_invoice_date' => now()->toDateString(),
                'exempt_amount' => $totalAmount,
                'taxable_base' => 0,
                'tax_amount' => 0,
                'total_amount' => $totalAmount,
                'exchange_rate' => $exchangeRate,
                'registered_by' => $adminId,
                'uploaded_by' => $adminId,
                'status' => 'pending',
                'status_payment' => 0,
            ]);

            // Crear los detalles de la factura
            foreach ($parsedItems as $index => $item) {
                $invoice->details()->create([
                    'product_id' => $item['product']->id,
                    'lot_number' => $item['lot_number'],
                    'expiration_date' => $item['expiration_date'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['total_cost'],
                    'location' => 'Por Asignar',
                    'tax_enabled' => false,
                    'display_order' => $index,
                ]);
            }

            // Instanciar el servicio para transicionar los estados de forma idéntica al sistema
            $invoiceActionService = app(\App\Services\Invoices\InvoiceActionService::class);

            // 1. Finalizar carga (pasa a 'loaded')
            $invoice = $invoiceActionService->finalizeInvoice($invoice);

            // 2. Aprobar factura (pasa a 'to_order' y genera trazabilidad/lotes)
            $invoice = $invoiceActionService->approveInvoice($invoice, ['payment_rule_id' => null]);

            // 3. Consolidar/ordenar (pasa a 'ordered' con ubicación inicial)
            $locationsData = [];
            foreach ($invoice->details as $detail) {
                $locationsData[] = [
                    'id' => $detail->id,
                    'location' => 'Por Asignar',
                ];
            }
            $invoice = $invoiceActionService->updateInvoiceLocations($invoice, ['details' => $locationsData]);

            // Limpiar estado
            Cache::forget('telegram_state_' . $fromId);

            // Responder con éxito
            $msg = "✅ *[FACTURA DE FRUTAS REGISTRADA Y PROCESADA]*\n\n"
                 . "🏢 *Proveedor:* {$supplier->name}\n"
                 . "🔢 *Factura Nº:* `{$invoiceNumber}`\n"
                 . "💰 *Monto Total:* " . number_format($totalAmount, 2) . " {$currency}\n"
                 . "📅 *Vencimiento y Pago:* {$expDate}\n"
                 . "📈 *Estado actual:* `Procesada y Ordenada (Consolidada)`\n\n"
                 . "📦 *Detalles registrados:*\n";

            foreach ($parsedItems as $item) {
                $msg .= "• *{$item['product']->name}*: {$item['quantity']} und / total: " . number_format($item['total_cost'], 2) . " {$currency} (Lote: `{$item['lot_number']}`)\n";
            }

            $this->telegramService->sendMessage($msg, $chatId);

        } catch (\Exception $e) {
            \Log::error('[TelegramWebhook] Error en registro rápido de frutas: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->telegramService->sendMessage("❌ Error al registrar la factura de frutas: " . $e->getMessage(), $chatId);
        }
    }
}
