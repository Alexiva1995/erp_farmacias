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
            if ($stateData['state'] === 'waiting_for_payment_amount') {
                $this->processUserProvidedPaymentAmount(trim($text), $fromId, $chatId, $stateData);
                return;
            }
            if ($stateData['state'] === 'waiting_for_payment_photo') {
                $lowerText = strtolower(trim($text));
                if ($lowerText === 'saltar' || $lowerText === 'ninguno') {
                    $this->skipPaymentPhoto($fromId, $chatId, $stateData);
                } else {
                    $this->telegramService->sendMessage("📸 Por favor envía la foto del comprobante de pago o escribe *saltar*.", $chatId);
                }
                return;
            }
            if ($stateData['state'] === 'waiting_for_payment_reference_manual') {
                $this->processUserProvidedPaymentReference(trim($text), $fromId, $chatId, $stateData);
                return;
            }
        }

        // Caso A: Se recibe una foto
        if (isset($message['photo'])) {
            if ($stateData && is_array($stateData) && $stateData['state'] === 'waiting_for_payment_photo') {
                $this->processPaymentPhoto($message['photo'], $fromId, $chatId, $stateData);
                return;
            }
            $this->processInvoicePhoto($message['photo'], $fromId, $chatId);
            return;
        }

        // Caso B: Comando para iniciar el registro de facturas
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

        // Caso F: Comando para gestionar pagos pendientes
        if ($cleanText === 'pagos' || $cleanText === '/pagos') {
            $this->initPaymentsFlow($fromId, $chatId);
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

        if (str_starts_with($callbackData, 'pay_supplier_')) {
            $supplierId = (int) substr($callbackData, strlen('pay_supplier_'));
            $this->startPaymentForSupplier($supplierId, $fromId, $callbackQueryId, $messageId, $chatId);
            return;
        }

        if (str_starts_with($callbackData, 'skip_supplier_')) {
            $supplierId = (int) substr($callbackData, strlen('skip_supplier_'));
            $this->skipSupplierInPaymentQueue($supplierId, $fromId, $callbackQueryId, $messageId, $chatId);
            return;
        }

        if ($callbackData === 'exit_payments') {
            $this->exitPaymentsFlow($fromId, $callbackQueryId, $messageId, $chatId);
            return;
        }

        if (str_starts_with($callbackData, 'pay_curr_')) {
            $currency = substr($callbackData, strlen('pay_curr_'));
            $this->selectPaymentCurrency($currency, $fromId, $callbackQueryId, $messageId, $chatId);
            return;
        }

        if (str_starts_with($callbackData, 'pay_method_')) {
            $method = substr($callbackData, strlen('pay_method_'));
            $this->selectPaymentMethod($method, $fromId, $callbackQueryId, $messageId, $chatId);
            return;
        }

        if ($callbackData === 'skip_payment_photo') {
            $this->skipPaymentPhotoFromCallback($fromId, $callbackQueryId, $messageId, $chatId);
            return;
        }

        if ($callbackData === 'confirm_payment_registration') {
            $this->confirmPaymentRegistration($fromId, $callbackQueryId, $messageId, $chatId);
            return;
        }

        if (str_starts_with($callbackData, 'approve_fruit_invoice_')) {
            $invoiceId = (int) substr($callbackData, strlen('approve_fruit_invoice_'));
            $this->approveFruitInvoiceFromCallback($invoiceId, $callbackQueryId, $messageId, $chatId);
            return;
        }

        if (str_starts_with($callbackData, 'keep_loaded_fruit_invoice_')) {
            $invoiceId = (int) substr($callbackData, strlen('keep_loaded_fruit_invoice_'));
            $this->keepLoadedFruitInvoiceFromCallback($invoiceId, $callbackQueryId, $messageId, $chatId);
            return;
        }

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

                // Buscar o crear el producto en la base de datos con IDs específicos si aplican
                $productNameLower = strtolower($productName);
                $productId = null;
                if (str_contains($productNameLower, 'fresa')) {
                    $productId = 1058;
                } elseif (str_contains($productNameLower, 'cambur')) {
                    $productId = 1059;
                } elseif (str_contains($productNameLower, 'kiwi')) {
                    $productId = 1060;
                }

                if ($productId) {
                    $product = \App\Models\Product::find($productId);
                } else {
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
                }

                if ($product) {
                    // Ajustar cantidad según la presentación del producto (para no registrar 3000 paquetes sino 3 paquetes de 1000g)
                    $presentation = (float) ($product->presentation ?? 1);
                    $finalQty = $presentation > 0 ? $quantity / $presentation : $quantity;
                    $unitCost = $finalQty > 0 ? $totalCost / $finalQty : 0;

                    // Generar número de lote automático correlativo
                    $lotCount = $product->lots()->count() + 1;
                    $lotNumber = 'L-' . date('Ymd') . '-' . $lotCount;
                    $expirationDate = now()->addDays(7)->toDateString();

                    $parsedItems[] = [
                        'product' => $product,
                        'quantity' => $finalQty,
                        'total_cost' => $totalCost,
                        'unit_cost' => $unitCost,
                        'lot_number' => $lotNumber,
                        'expiration_date' => $expirationDate,
                    ];
                }
            }
        }

        if (empty($parsedItems)) {
            $this->telegramService->sendMessage("❌ No se pudo procesar ningún producto válido. Asegúrate de usar el formato correcto:\n`Fresa 2000g 18.000 COP - Cambur 1000 2.000 COP`", $chatId);
            return;
        }

        // 2. Calcular total de la factura
        $totalAmount = array_sum(array_column($parsedItems, 'total_cost'));

        // Obtener tasa de cambio (si es COP, se usa la tasa COPC que es la configurada para vueltos/efectivo)
        $exchangeRate = 1;
        if ($currency !== 'USD') {
            $rateCurrency = $currency === 'COP' ? 'COPC' : $currency;
            $exchangeRate = app(\App\Services\Resources\ResourceService::class)->getExchangeRate($rateCurrency) ?? 1;
        }

        // Obtener usuario administrador para el registro de auditoría
        $adminId = \App\Models\User::first()?->id ?? 1;
        $invoiceNumber = 'FRUTA-' . date('YmdHis');
        $expDate = now()->addDays(7)->toDateString();

        try {
            // Autenticar temporalmente para evitar que falle en observadores o servicios
            \Illuminate\Support\Facades\Auth::loginUsingId($adminId);

            // Instanciar el servicio para transicionar los estados de forma idéntica al sistema
            $invoiceActionService = app(\App\Services\Invoices\InvoiceActionService::class);

            // Crear la factura usando el servicio
            $invoice = $invoiceActionService->createInvoice([
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
            ]);

            // Formatear detalles para el servicio
            $detailsPayload = [];
            foreach ($parsedItems as $index => $item) {
                $detailsPayload[] = [
                    'product' => ['id' => $item['product']->id],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'lot_number' => $item['lot_number'],
                    'expiration_date' => $item['expiration_date'],
                    'tax_enabled' => false,
                    'is_return' => false,
                    'location' => null,
                    'display_order' => $index,
                ];
            }

            // Guardar detalles usando el servicio
            $invoice = $invoiceActionService->saveInvoiceDetails($invoice, [
                'invoice' => [
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
                ],
                'details' => $detailsPayload,
            ]);

            // 1. Finalizar carga (pasa a 'loaded')
            $invoice = $invoiceActionService->finalizeInvoice($invoice);

            // Limpiar estado
            Cache::forget('telegram_state_' . $fromId);

            // Enviar mensaje con botones interactivos de aprobación
            $token = config('services.telegram.bot_token');
            $msg = "✅ *[FACTURA DE FRUTAS CARGADA]*\n\n"
                 . "🏢 *Proveedor:* {$supplier->name}\n"
                 . "🔢 *Factura Nº:* `{$invoiceNumber}`\n"
                 . "💰 *Monto Total:* " . number_format($totalAmount, 2) . " {$currency}\n"
                 . "📅 *Vencimiento y Pago:* {$expDate}\n"
                 . "📈 *Estado actual:* `Cargada (loaded)`\n\n"
                 . "📦 *Detalles registrados:*\n";

            foreach ($parsedItems as $item) {
                $msg .= "• *{$item['product']->name}*: {$item['quantity']} und / total: " . number_format($item['total_cost'], 2) . " {$currency} (Lote: `{$item['lot_number']}`)\n";
            }

            $msg .= "\n¿Deseas aprobar esta factura ahora para generar los movimientos de inventario y lotes?";

            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $msg,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Sí, Aprobar', 'callback_data' => "approve_fruit_invoice_{$invoice->id}"],
                            ['text' => '📁 No, Dejar Cargada', 'callback_data' => "keep_loaded_fruit_invoice_{$invoice->id}"],
                        ]
                    ]
                ])
            ]);

        } catch (\Exception $e) {
            \Log::error('[TelegramWebhook] Error en registro rápido de frutas: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->telegramService->sendMessage("❌ Error al registrar la factura de frutas: " . $e->getMessage(), $chatId);
        }
    }

    /**
     * Callback para aprobar la factura de frutas (llevar a to_order y generar inventario).
     */
    protected function approveFruitInvoiceFromCallback(int $invoiceId, string $callbackQueryId, ?int $messageId, ?int $chatId): void
    {
        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            $this->answerCallback($callbackQueryId, 'La factura no existe.');
            return;
        }

        try {
            $adminId = \App\Models\User::first()?->id ?? 1;
            \Illuminate\Support\Facades\Auth::loginUsingId($adminId);

            $invoiceActionService = app(\App\Services\Invoices\InvoiceActionService::class);
            $invoice = $invoiceActionService->approveInvoice($invoice, ['payment_rule_id' => null]);

            $this->answerCallback($callbackQueryId, 'Factura aprobada con éxito.');

            $token = config('services.telegram.bot_token');
            $msg = "✅ *[FACTURA DE FRUTAS APROBADA]*\n\n"
                 . "🏢 *Proveedor:* {$invoice->supplier->name}\n"
                 . "🔢 *Factura Nº:* `{$invoice->invoice_number}`\n"
                 . "💰 *Monto Total:* " . number_format($invoice->total_amount, 2) . " {$invoice->currency}\n"
                 . "📅 *Vencimiento y Pago:* {$invoice->exp_date}\n"
                 . "📈 *Estado actual:* `Por Ordenar (to_order)`\n\n"
                 . "🚀 *Los movimientos de inventario y lotes físicos han sido generados exitosamente. La factura está lista para ser ordenada.*";

            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $msg,
                'parse_mode' => 'Markdown',
            ]);

        } catch (\Exception $e) {
            \Log::error('[TelegramWebhook] Error al aprobar factura desde botón: ' . $e->getMessage());
            $this->answerCallback($callbackQueryId, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Callback para mantener la factura en estado cargada (loaded).
     */
    protected function keepLoadedFruitInvoiceFromCallback(int $invoiceId, string $callbackQueryId, ?int $messageId, ?int $chatId): void
    {
        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice) {
            $this->answerCallback($callbackQueryId, 'La factura no existe.');
            return;
        }

        $this->answerCallback($callbackQueryId, 'Se conservó en estado cargada.');

        $token = config('services.telegram.bot_token');
        $msg = "📁 *[FACTURA CONSERVADA EN CARGADA]*\n\n"
             . "🏢 *Proveedor:* {$invoice->supplier->name}\n"
             . "🔢 *Factura Nº:* `{$invoice->invoice_number}`\n"
             . "💰 *Monto Total:* " . number_format($invoice->total_amount, 2) . " {$invoice->currency}\n"
             . "📅 *Vencimiento y Pago:* {$invoice->exp_date}\n"
             . "📈 *Estado actual:* `Cargada (loaded)`\n\n"
             . "ℹ️ *La factura se mantiene en estado cargada. Puedes aprobarla y gestionarla desde el panel web.*";

        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Inicializar el flujo de pagos consultando las deudas consolidadas.
     */
    protected function initPaymentsFlow($fromId, $chatId): void
    {
        try {
            // Consultar facturas pendientes de pago (status_payment != 1 o nulo) en estado ordered (por pagar) y vencidas/venciendo al día de hoy
            $invoices = \App\Models\Invoice::with(['supplier'])
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                      ->orWhere('status_payment', '!=', 1);
                })
                ->where('status', 'ordered')
                ->whereDate('payment_date', '<=', now()->toDateString())
                ->get();

            if ($invoices->isEmpty()) {
                $this->telegramService->sendMessage("🎉 *[EXCELENTE]*\n\nNo tienes proveedores con deudas pendientes al día de hoy.", $chatId);
                return;
            }

            // Agrupar por proveedor y consolidar deuda
            $grouped = $invoices->groupBy('supplier_id');
            $suppliersWithDebt = [];

            foreach ($grouped as $supplierId => $group) {
                $firstInvoice = $group->first();
                $supplierName = $firstInvoice->supplier->name ?? 'Desconocido';

                // Calcular deuda restante restando pagos parciales
                $totalAmountUSD = $group->sum('total_usd');
                $invoiceIds = $group->pluck('id')->toArray();

                $payments = \App\Models\InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
                    $query->whereIn('id', $invoiceIds);
                })->get();

                $totalPaidUSD = 0;
                foreach ($payments as $payment) {
                    if ($payment->payment_method === 'USD') {
                        $totalPaidUSD += $payment->amount;
                    } else {
                        $exchangeRate = \App\Models\ExchangeRate::where('currency_code', $payment->payment_method)->first();
                        if ($exchangeRate) {
                            $totalPaidUSD += round($payment->amount / $exchangeRate->rate, 2);
                        }
                    }
                }

                $remainingAmountUSD = max(0, $totalAmountUSD - $totalPaidUSD);

                if ($remainingAmountUSD <= 0.01) {
                    continue; // Ya está pagado en la práctica
                }

                // Recopilar la lista de facturas individuales con su saldo pendiente
                $invoicesList = [];
                foreach ($group as $invoice) {
                    $invoicePayments = \App\Models\InvoicePayment::whereHas('invoices', function ($query) use ($invoice) {
                        $query->where('id', $invoice->id);
                    })->get();

                    $invoicePaidUSD = 0;
                    foreach ($invoicePayments as $p) {
                        if ($p->payment_method === 'USD') {
                            $invoicePaidUSD += $p->amount;
                        } else {
                            $exRate = \App\Models\ExchangeRate::where('currency_code', $p->payment_method)->first();
                            if ($exRate) {
                                $invoicePaidUSD += round($p->amount / $exRate->rate, 2);
                            }
                        }
                    }

                    $invoiceRemainingUSD = max(0, $invoice->total_usd - $invoicePaidUSD);
                    
                    if ($invoiceRemainingUSD <= 0.01) {
                        continue;
                    }

                    $invoiceRemainingOriginal = $invoice->total_amount;
                    if ($invoice->currency === 'Bs') {
                        $rate = \App\Models\ExchangeRate::where('currency_code', 'VES')->first()?->rate ?? 1;
                        $invoiceRemainingOriginal = round($invoiceRemainingUSD * $rate, 2);
                    } elseif ($invoice->currency === 'COP') {
                        $rate = \App\Models\ExchangeRate::where('currency_code', 'COP')->first()?->rate ?? 1;
                        $invoiceRemainingOriginal = round($invoiceRemainingUSD * $rate, 2);
                    } else {
                        $invoiceRemainingOriginal = $invoiceRemainingUSD;
                    }

                    $invoicesList[] = [
                        'invoice_number' => $invoice->invoice_number,
                        'remaining_amount' => $invoiceRemainingOriginal,
                        'currency' => $invoice->currency,
                        'remaining_usd' => $invoiceRemainingUSD,
                    ];
                }

                // Formatear monedas
                $currency = $firstInvoice->currency;
                $remainingOriginal = $group->sum('total_amount');

                if ($currency === 'Bs') {
                    $rate = \App\Models\ExchangeRate::where('currency_code', 'VES')->first()?->rate ?? 1;
                    $remainingOriginal = round($remainingAmountUSD * $rate, 2);
                } elseif ($currency === 'COP') {
                    $rate = \App\Models\ExchangeRate::where('currency_code', 'COP')->first()?->rate ?? 1;
                    $remainingOriginal = round($remainingAmountUSD * $rate, 2);
                } else {
                    $remainingOriginal = $remainingAmountUSD;
                }

                $formattedDebt = number_format($remainingOriginal, 2) . ' ' . $currency;
                if ($currency !== 'USD') {
                    $formattedDebt .= " (≈ " . number_format($remainingAmountUSD, 2) . " USD)";
                }

                $suppliersWithDebt[] = [
                    'id' => $supplierId,
                    'name' => $supplierName,
                    'pending_amount_usd' => $remainingAmountUSD,
                    'pending_amount_original' => $remainingOriginal,
                    'currency' => $currency,
                    'formatted_debt' => $formattedDebt,
                    'invoice_ids' => $invoiceIds,
                    'invoices' => $invoicesList,
                    'invoice_count' => count($invoicesList)
                ];
            }

            if (empty($suppliersWithDebt)) {
                $this->telegramService->sendMessage("🎉 *[EXCELENTE]*\n\nNo tienes proveedores con deudas pendientes al día de hoy.", $chatId);
                return;
            }

            // Guardar en la cola y empezar
            Cache::put('telegram_payments_queue_' . $fromId, [
                'suppliers' => $suppliersWithDebt,
                'index' => 0
            ], 1800);

            $this->sendNextSupplierPaymentPrompt($fromId, $chatId);

        } catch (\Exception $e) {
            \Log::error('[TelegramWebhook] Error al inicializar flujo de pagos: ' . $e->getMessage());
            $this->telegramService->sendMessage("❌ Error al cargar las deudas pendientes: " . $e->getMessage(), $chatId);
        }
    }

    /**
     * Enviar el prompt del siguiente proveedor con deuda en la cola.
     */
    protected function sendNextSupplierPaymentPrompt($fromId, $chatId, ?int $editMessageId = null): void
    {
        $queue = Cache::get('telegram_payments_queue_' . $fromId);
        if (!$queue || !isset($queue['suppliers']) || $queue['index'] >= count($queue['suppliers'])) {
            $msg = "✅ *[PAGOS COMPLETADOS]*\n\nNo quedan más proveedores con deudas pendientes por revisar en tu cola actual.";
            if ($editMessageId) {
                $token = config('services.telegram.bot_token');
                \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
                    'chat_id' => $chatId,
                    'message_id' => $editMessageId,
                    'text' => $msg,
                    'parse_mode' => 'Markdown',
                ]);
            } else {
                $this->telegramService->sendMessage($msg, $chatId);
            }
            Cache::forget('telegram_payments_queue_' . $fromId);
            Cache::forget('telegram_state_' . $fromId);
            return;
        }

        $supplier = $queue['suppliers'][$queue['index']];

        // Construir el listado detallado de facturas
        $invoicesText = "";
        foreach ($supplier['invoices'] as $inv) {
            $invoicesText .= "• *Factura # {$inv['invoice_number']}:* " . number_format($inv['remaining_amount'], 2) . " {$inv['currency']}";
            if ($inv['currency'] !== 'USD') {
                $invoicesText .= " (≈ " . number_format($inv['remaining_usd'], 2) . " USD)";
            }
            $invoicesText .= "\n";
        }

        $msg = "💳 *[PAGO DE PROVEEDOR]*\n\n"
             . "🏢 *Proveedor:* *{$supplier['name']}*\n"
             . "💰 *Monto de Deuda Total:* `{$supplier['formatted_debt']}`\n\n"
             . "📄 *Facturas Pendientes (al día de hoy):*\n"
             . $invoicesText . "\n"
             . "¿Deseas registrar un pago para este proveedor ahora?";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '💳 Registrar Pago', 'callback_data' => "pay_supplier_{$supplier['id']}"],
                    ['text' => '⏭️ Saltar', 'callback_data' => "skip_supplier_{$supplier['id']}"],
                ],
                [
                    ['text' => '❌ Salir del Flujo', 'callback_data' => "exit_payments"]
                ]
            ]
        ];

        if ($editMessageId) {
            $token = config('services.telegram.bot_token');
            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $editMessageId,
                'text' => $msg,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($replyMarkup)
            ]);
        } else {
            $this->telegramService->sendMessage($msg, $chatId, $replyMarkup);
        }
    }

    /**
     * Iniciar el proceso de pago para el proveedor seleccionado.
     */
    protected function startPaymentForSupplier(int $supplierId, $fromId, string $callbackQueryId, ?int $messageId, ?int $chatId): void
    {
        $queue = Cache::get('telegram_payments_queue_' . $fromId);
        if (!$queue) {
            $this->answerCallback($callbackQueryId, 'Sesión expirada.');
            return;
        }

        $supplier = $queue['suppliers'][$queue['index']];

        // Guardar estado
        Cache::put('telegram_state_' . $fromId, [
            'state' => 'waiting_for_payment_currency',
            'supplier_id' => $supplier['id'],
            'supplier_name' => $supplier['name'],
            'invoice_ids' => $supplier['invoice_ids'],
            'total_debt' => $supplier['formatted_debt'],
            'total_debt_usd' => $supplier['pending_amount_usd']
        ], 600);

        $this->answerCallback($callbackQueryId, 'Iniciando registro de pago.');

        $msg = "💱 *[REGISTRAR PAGO - PASO 1]*\n\n"
             . "🏢 *Proveedor:* {$supplier['name']}\n"
             . "💰 *Deuda:* `{$supplier['formatted_debt']}`\n\n"
             . "Selecciona la **moneda** con la que realizarás el pago:";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '💵 USD', 'callback_data' => 'pay_curr_USD'],
                    ['text' => '🇨🇴 COP', 'callback_data' => 'pay_curr_COP'],
                    ['text' => '🇻🇪 Bs (VES)', 'callback_data' => 'pay_curr_VES'],
                ],
                [
                    ['text' => '❌ Cancelar', 'callback_data' => 'exit_payments']
                ]
            ]
        ];

        $token = config('services.telegram.bot_token');
        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($replyMarkup)
        ]);
    }

    /**
     * Saltar el proveedor actual.
     */
    protected function skipSupplierInPaymentQueue(int $supplierId, $fromId, string $callbackQueryId, ?int $messageId, ?int $chatId): void
    {
        $queue = Cache::get('telegram_payments_queue_' . $fromId);
        if ($queue) {
            $queue['index']++;
            Cache::put('telegram_payments_queue_' . $fromId, $queue, 1800);
            $this->answerCallback($callbackQueryId, 'Saltando proveedor.');
            $this->sendNextSupplierPaymentPrompt($fromId, $chatId, $messageId);
        } else {
            $this->answerCallback($callbackQueryId, 'Sesión expirada.');
        }
    }

    /**
     * Salir del flujo de pagos.
     */
    protected function exitPaymentsFlow($fromId, string $callbackQueryId, ?int $messageId, ?int $chatId): void
    {
        Cache::forget('telegram_payments_queue_' . $fromId);
        Cache::forget('telegram_state_' . $fromId);
        $this->answerCallback($callbackQueryId, 'Flujo cancelado.');

        $token = config('services.telegram.bot_token');
        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "❌ *[FLUJO DE PAGOS CANCELADO]*\n\nHas salido del gestor de pagos pendientes.",
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Al seleccionar la moneda del pago.
     */
    protected function selectPaymentCurrency(string $currency, $fromId, string $callbackQueryId, ?int $messageId, ?int $chatId): void
    {
        $stateData = Cache::get('telegram_state_' . $fromId);
        if (!$stateData || $stateData['state'] !== 'waiting_for_payment_currency') {
            $this->answerCallback($callbackQueryId, 'Sesión de pago inválida.');
            return;
        }

        $stateData['payment_currency'] = $currency;
        $stateData['state'] = 'waiting_for_payment_method';
        Cache::put('telegram_state_' . $fromId, $stateData, 600);

        $this->answerCallback($callbackQueryId, "Moneda: {$currency}");

        $msg = "💳 *[REGISTRAR PAGO - PASO 2]*\n\n"
             . "🏢 *Proveedor:* {$stateData['supplier_name']}\n"
             . "💰 *Deuda:* `{$stateData['total_debt']}`\n"
             . "💱 *Moneda seleccionada:* `{$currency}`\n\n"
             . "Selecciona el **método de pago**:";

        // Generar botones según la moneda (idéntico al sistema)
        $buttons = [];
        if ($currency === 'USD') {
            $buttons[] = [['text' => '💵 Efectivo (CASH)', 'callback_data' => 'pay_method_CASH']];
            $buttons[] = [['text' => '🔸 Binance (BINANCE)', 'callback_data' => 'pay_method_BINANCE']];
            $buttons[] = [['text' => '🔵 PayPal (PAYPAL)', 'callback_data' => 'pay_method_PAYPAL']];
            $buttons[] = [['text' => '📊 Crédito (CREDIT)', 'callback_data' => 'pay_method_CREDIT']];
        } elseif ($currency === 'COP') {
            $buttons[] = [['text' => '💵 Efectivo COP (CASH)', 'callback_data' => 'pay_method_CASH']];
            $buttons[] = [['text' => '🏛️ Transferencia (TRANSFER)', 'callback_data' => 'pay_method_TRANSFER']];
        } else { // VES / Bs
            $buttons[] = [['text' => '💵 Efectivo Bs (CASH)', 'callback_data' => 'pay_method_CASH']];
            $buttons[] = [['text' => '💳 Tarjeta (CARD)', 'callback_data' => 'pay_method_CARD']];
            $buttons[] = [['text' => '📱 Pago Móvil (MOBILE)', 'callback_data' => 'pay_method_MOBILE']];
            $buttons[] = [['text' => '🏛️ Transferencia (TRANSFER)', 'callback_data' => 'pay_method_TRANSFER']];
        }
        $buttons[] = [['text' => '❌ Cancelar', 'callback_data' => 'exit_payments']];

        $token = config('services.telegram.bot_token');
        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]);
    }

    /**
     * Al seleccionar el método de pago.
     */
    protected function selectPaymentMethod(string $method, $fromId, string $callbackQueryId, ?int $messageId, ?int $chatId): void
    {
        $stateData = Cache::get('telegram_state_' . $fromId);
        if (!$stateData || $stateData['state'] !== 'waiting_for_payment_method') {
            $this->answerCallback($callbackQueryId, 'Sesión de pago inválida.');
            return;
        }

        $stateData['payment_method'] = $method;
        $stateData['state'] = 'waiting_for_payment_amount';
        Cache::put('telegram_state_' . $fromId, $stateData, 600);

        $this->answerCallback($callbackQueryId, "Método: {$method}");

        $msg = "💰 *[REGISTRAR PAGO - PASO 3]*\n\n"
             . "🏢 *Proveedor:* {$stateData['supplier_name']}\n"
             . "💰 *Deuda:* `{$stateData['total_debt']}`\n"
             . "💱 *Moneda:* `{$stateData['payment_currency']}`\n"
             . "💳 *Método:* `{$method}`\n\n"
             . "Por favor, escribe el **monto a pagar** (envíalo como mensaje de texto, ej: `150` o `150.50`):";

        $token = config('services.telegram.bot_token');
        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Procesar el monto escrito por el usuario.
     */
    protected function processUserProvidedPaymentAmount(string $text, $fromId, $chatId, array $stateData): void
    {
        // Limpiar precio
        $priceStr = str_replace(['$', ' ', ','], ['', '', '.'], $text);
        if (!is_numeric($priceStr) || (float) $priceStr <= 0) {
            $this->telegramService->sendMessage("❌ El monto ingresado no es válido. Por favor ingresa un número mayor a 0 (ej: `120` o `120.50`):", $chatId);
            return;
        }

        $amount = (float) $priceStr;
        $stateData['payment_amount'] = $amount;
        $stateData['state'] = 'waiting_for_payment_photo';
        Cache::put('telegram_state_' . $fromId, $stateData, 600);

        $msg = "📸 *[REGISTRAR PAGO - PASO 4]*\n\n"
             . "🏢 *Proveedor:* {$stateData['supplier_name']}\n"
             . "💰 *Monto a Pagar:* " . number_format($amount, 2) . " {$stateData['payment_currency']}\n\n"
             . "Por favor, envía la **foto del comprobante de pago** (capture de pantalla de la transferencia, Zelle, etc.).\n\n"
             . "_Si no tienes foto o prefieres no subirla, escribe *saltar* o presiona el botón de abajo._";

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => '⏭️ Saltar Foto', 'callback_data' => 'skip_payment_photo']
                ],
                [
                    ['text' => '❌ Cancelar', 'callback_data' => 'exit_payments']
                ]
            ]
        ];

        $this->telegramService->sendMessage($msg, $chatId, $replyMarkup);
    }

    /**
     * Saltar foto desde texto.
     */
    protected function skipPaymentPhoto($fromId, $chatId, array $stateData): void
    {
        $stateData['photo_url'] = null;
        $stateData['state'] = 'waiting_for_payment_reference_manual';
        Cache::put('telegram_state_' . $fromId, $stateData, 600);

        $msg = "📝 *[REFERENCIA DE TRANSACCIÓN]*\n\n"
             . "Por favor, escribe el **número de referencia** de la transacción (o escribe `ninguno` si no aplica):";

        $this->telegramService->sendMessage($msg, $chatId);
    }

    /**
     * Saltar foto desde callback.
     */
    protected function skipPaymentPhotoFromCallback($fromId, string $callbackQueryId, ?int $messageId, ?int $chatId): void
    {
        $stateData = Cache::get('telegram_state_' . $fromId);
        if (!$stateData || $stateData['state'] !== 'waiting_for_payment_photo') {
            $this->answerCallback($callbackQueryId, 'Sesión inválida.');
            return;
        }

        $stateData['photo_url'] = null;
        $stateData['state'] = 'waiting_for_payment_reference_manual';
        Cache::put('telegram_state_' . $fromId, $stateData, 600);

        $this->answerCallback($callbackQueryId, 'Foto saltada.');

        $msg = "📝 *[REFERENCIA DE TRANSACCIÓN]*\n\n"
             . "Por favor, escribe el **número de referencia** de la transacción (o escribe `ninguno` si no aplica):";

        $token = config('services.telegram.bot_token');
        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Procesar la foto enviada por el usuario.
     */
    protected function processPaymentPhoto(array $photoArray, $fromId, $chatId, array $stateData): void
    {
        $this->telegramService->sendMessage("⚡ *[PROCESANDO COMPROBANTE]*\n\nAnalizando la imagen con Inteligencia Artificial para extraer el número de referencia...", $chatId);

        try {
            // Obtener archivo de Telegram
            $largestPhoto = end($photoArray);
            $fileId = $largestPhoto['file_id'];
            $fileUrl = $this->telegramService->getFileUrl($fileId);

            if (!$fileUrl) {
                throw new \Exception('No se pudo obtener la URL de descarga de la foto de Telegram.');
            }

            // Guardar archivo temporalmente
            $tempDir = storage_path('app/temp_payments');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $extension = pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $tempPath = $tempDir . '/' . uniqid('pay_') . '.' . $extension;
            file_put_contents($tempPath, file_get_contents($fileUrl));

            // Llamar a Gemini para extraer la referencia
            $geminiService = app(\App\Services\GeminiService::class);
            $reference = $geminiService->extractPaymentReference($tempPath);

            // Guardar la foto en la carpeta pública del ERP
            $publicPaymentsDir = public_path('uploads/payments');
            if (!file_exists($publicPaymentsDir)) {
                mkdir($publicPaymentsDir, 0755, true);
            }
            $finalFileName = uniqid('pay_img_') . '.' . $extension;
            $finalPath = $publicPaymentsDir . '/' . $finalFileName;
            rename($tempPath, $finalPath);

            $stateData['photo_url'] = '/uploads/payments/' . $finalFileName;

            if (!empty($reference)) {
                $stateData['reference'] = $reference;
                $stateData['state'] = 'waiting_for_payment_reference_confirm';
                Cache::put('telegram_state_' . $fromId, $stateData, 600);

                $msg = "🔍 *[REFERENCIA DETECTADA]*\n\n"
                     . "Se ha extraído el número de referencia:\n"
                     . "👉 `{$reference}`\n\n"
                     . "¿Es correcto? Si es correcto presiona el botón. Si no es correcto, simplemente escribe el número de referencia correcto:";

                $replyMarkup = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Sí, Confirmar y Registrar', 'callback_data' => 'confirm_payment_registration']
                        ]
                    ]
                ];
                $this->telegramService->sendMessage($msg, $chatId, $replyMarkup);
            } else {
                // No se pudo detectar, pedir manual
                $stateData['state'] = 'waiting_for_payment_reference_manual';
                Cache::put('telegram_state_' . $fromId, $stateData, 600);

                $msg = "⚠️ *[REFERENCIA NO DETECTADA]*\n\n"
                     . "No se pudo extraer de forma automática el número de referencia del comprobante.\n\n"
                     . "Por favor, escribe el **número de referencia** manualmente para completar el pago:";
                $this->telegramService->sendMessage($msg, $chatId);
            }

        } catch (\Exception $e) {
            \Log::error('[TelegramWebhook] Error al procesar comprobante de pago: ' . $e->getMessage());
            $stateData['state'] = 'waiting_for_payment_reference_manual';
            Cache::put('telegram_state_' . $fromId, $stateData, 600);

            $this->telegramService->sendMessage("⚠️ Ocurrió un inconveniente al analizar el comprobante. Por favor, escribe el **número de referencia** manualmente:", $chatId);
        }
    }

    /**
     * Procesar la referencia escrita manualmente.
     */
    protected function processUserProvidedPaymentReference(string $text, $fromId, $chatId, array $stateData): void
    {
        $ref = trim($text);
        $stateData['reference'] = (strtolower($ref) === 'ninguno') ? null : $ref;
        
        // Ejecutar el pago inmediatamente
        $this->executeTelegramPayment($fromId, $chatId, $stateData);
    }

    /**
     * Confirmar el pago cuando el OCR fue correcto y el usuario presionó el botón de confirmar.
     */
    protected function confirmPaymentRegistration($fromId, string $callbackQueryId, ?int $messageId, ?int $chatId): void
    {
        $stateData = Cache::get('telegram_state_' . $fromId);
        if (!$stateData) {
            $this->answerCallback($callbackQueryId, 'Sesión inválida.');
            return;
        }

        $this->answerCallback($callbackQueryId, 'Registrando pago...');
        $this->executeTelegramPayment($fromId, $chatId, $stateData);
    }

    /**
     * Registrar el pago en la base de datos siguiendo la arquitectura del ERP.
     */
    protected function executeTelegramPayment($fromId, $chatId, array $stateData): void
    {
        $adminId = \App\Models\User::first()?->id ?? 1;

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // Autenticar temporalmente para evitar problemas de auditoría
            \Illuminate\Support\Facades\Auth::loginUsingId($adminId);

            $invoiceIds = $stateData['invoice_ids'];
            $paymentCurrency = $stateData['payment_currency'];
            $paymentAmount = $stateData['payment_amount'];
            $paymentMethod = $stateData['payment_method'];
            $reference = $stateData['reference'] ?? null;
            $photoUrl = $stateData['photo_url'] ?? null;

            // 1. Obtener facturas y calcular monto total de la deuda
            $invoices = \App\Models\Invoice::whereIn('id', $invoiceIds)->get();
            
            // Tasa de cambio de la moneda del pago
            $normalizedCurrency = ($paymentCurrency === 'Bs') ? 'VES' : $paymentCurrency;
            $exchangeRate = \App\Models\ExchangeRate::where('currency_code', $normalizedCurrency)->first();
            $rateValue = $exchangeRate ? (float) $exchangeRate->rate : 1.0000;

            // Calcular monto equivalente en USD
            if ($paymentCurrency === 'USD') {
                $amountUSD = $paymentAmount;
            } else {
                $amountUSD = round($paymentAmount / $rateValue, 2);
            }

            // Calcular deuda total restante en USD de estas facturas antes de este pago
            $totalInvoiceDebtUSD = 0;
            foreach ($invoices as $invoice) {
                $invoicePayments = \App\Models\InvoicePayment::whereHas('invoices', function ($query) use ($invoice) {
                    $query->where('id', $invoice->id);
                })->get();

                $totalPaidUSD = 0;
                foreach ($invoicePayments as $p) {
                    if ($p->payment_method === 'USD') {
                        $totalPaidUSD += $p->amount;
                    } else {
                        $exRate = \App\Models\ExchangeRate::where('currency_code', $p->payment_method)->first();
                        if ($exRate) {
                            $totalPaidUSD += round($p->amount / $exRate->rate, 2);
                        }
                    }
                }
                $totalInvoiceDebtUSD += max(0, $invoice->total_usd - $totalPaidUSD);
            }

            // 2. Registrar el pago en invoice_payments
            $payment = \App\Models\InvoicePayment::create([
                'payment_date' => now()->toDateString(),
                'amount' => $paymentAmount,
                'payment_method' => $normalizedCurrency,
                'reference' => $reference,
                'status' => 'paid',
                'payment_by' => $adminId,
                'photo_url' => $photoUrl,
                'method' => $paymentMethod
            ]);

            // 3. Crear relaciones pivot
            foreach ($invoiceIds as $invoiceId) {
                \Illuminate\Support\Facades\DB::table('invoice_payment_invoice')->insert([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                ]);
            }

            // 4. Determinar estado de pago (Completo si cubre el total con tolerancia)
            $tolerance = 0.05;
            $isFullPayment = ($amountUSD >= ($totalInvoiceDebtUSD - $tolerance));
            $paymentStatus = $isFullPayment ? 1 : 0; // 1 = Pagado, 0 = Pendiente (pago parcial)

            // Actualizar facturas
            $updateData = [
                'status' => 'ordered',
                'status_payment' => $paymentStatus,
                'updated_at' => now(),
            ];
            if ($isFullPayment) {
                $updateData['payment_date'] = now()->toDateString();
            }
            \App\Models\Invoice::whereIn('id', $invoiceIds)->update($updateData);

            // 5. Crear registro de Gasto (Expense)
            $category = \App\Models\ExpenseCategory::firstOrCreate(['name' => 'Pagos de Facturas']);
            $firstInvoice = $invoices->first();

            $mapping = [
                'CASH' => 'Efectivo',
                'CARD' => 'Tarjeta',
                'MOBILE' => 'Pago Móvil',
                'TRANSFER' => 'Transferencia',
                'BINANCE' => 'Binance',
                'PAYPAL' => 'PayPal',
                'CREDIT' => 'Crédito',
            ];
            $countValue = $mapping[$paymentMethod] ?? 'Efectivo';

            $expenseRate = $firstInvoice->is_indexed ? $rateValue : ($firstInvoice->currency === 'USD' ? 1.0000 : $firstInvoice->exchange_rate);
            $taxAmount = $firstInvoice->is_indexed ? ($firstInvoice->tax_amount / $firstInvoice->exchange_rate) * $expenseRate ?? 0 : ($firstInvoice->tax_amount ?? 0);

            \App\Models\Expense::create([
                'name' => "Pago Factura # {$firstInvoice->invoice_number} - Proveedor: {$firstInvoice->supplier->name}",
                'category_id' => $category->id,
                'amount' => $paymentAmount,
                'conversion_rate' => $rateValue,
                'currency' => $normalizedCurrency,
                'expense_date' => $payment->payment_date,
                'user_id' => $adminId,
                'has_invoice' => true,
                'is_deductible' => true,
                'tax_amount' => $taxAmount,
                'total_usd' => $amountUSD,
                'invoice_number' => $firstInvoice->invoice_number,
                'invoice_date' => $firstInvoice->created_invoice_date,
                'control_number' => $firstInvoice->control_number,
                'type_of_expense' => 'Normal',
                'count' => $countValue,
            ]);

            // 6. Registrar Transacción de caja
            \App\Models\Transaction::create([
                'user_id' => $adminId,
                'category_id' => $category->id,
                'exchange_rate' => $rateValue,
                'description' => substr("Pago factura(s) # {$invoices->pluck('invoice_number')->join(', ')} {$firstInvoice->supplier->name}", 0, 1000),
                'currency' => $normalizedCurrency,
                'type' => $paymentMethod,
                'amount' => $paymentAmount,
                'movement_type' => 'OUT',
                'transaction_date' => $payment->payment_date,
            ]);

            \Illuminate\Support\Facades\DB::commit();

            // 7. Responder con éxito y continuar con la cola
            $statusText = $isFullPayment ? 'Pago Completo (Liquidado) 🟩' : 'Pago Parcial (Saldo Restante) 🟨';
            $remainingText = $isFullPayment ? '0.00 USD' : number_format(max(0, $totalInvoiceDebtUSD - $amountUSD), 2) . ' USD';

            $msg = "✅ *[PAGO PROCESADO EXITOSAMENTE]*\n\n"
                 . "🏢 *Proveedor:* {$stateData['supplier_name']}\n"
                 . "💰 *Monto Pagado:* " . number_format($paymentAmount, 2) . " {$paymentCurrency}\n"
                 . "📈 *Estatus del Pago:* `{$statusText}`\n"
                 . "💵 *Monto en USD:* " . number_format($amountUSD, 2) . " USD\n"
                 . "📝 *Referencia:* " . ($reference ?: 'Ninguna') . "\n"
                 . "⚖️ *Saldo Restante:* `{$remainingText}`\n\n"
                 . "_El pago ha quedado asentado en el histórico, egresos y cierre de caja del ERP._";

            $this->telegramService->sendMessage($msg, $chatId);

            // Avanzar en la cola de proveedores
            $queue = Cache::get('telegram_payments_queue_' . $fromId);
            if ($queue) {
                $queue['index']++;
                Cache::put('telegram_payments_queue_' . $fromId, $queue, 1800);
            }

            // Limpiar estado temporal de este pago
            Cache::forget('telegram_state_' . $fromId);

            // Presentar el siguiente proveedor
            $this->sendNextSupplierPaymentPrompt($fromId, $chatId);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Log::error('[TelegramWebhook] Error al procesar pago: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->telegramService->sendMessage("❌ Error al registrar el pago en la base de datos: " . $e->getMessage(), $chatId);
            Cache::forget('telegram_state_' . $fromId);
        }
    }
}
