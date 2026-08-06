<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Telegram\StoreTelegramChannelRequest;
use App\Http\Requests\Telegram\ToggleTelegramCommandRequest;
use App\Http\Requests\Telegram\UpdateTelegramChannelRequest;
use App\Http\Requests\Telegram\UpdateTelegramCommandRequest;
use App\Http\Requests\Telegram\UpdateTelegramConfigRequest;
use App\Http\Resources\TelegramChannelResource;
use App\Http\Resources\TelegramCommandResource;
use App\Http\Resources\TelegramConfigResource;
use App\Models\TelegramChannel;
use App\Models\TelegramCommand;
use App\Models\TelegramConfig;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;

class TelegramConfigController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Obtener la configuración global de Telegram.
     */
    public function getConfig(): JsonResponse
    {
        $config = TelegramConfig::with(['channels'])->firstOrCreate(
            ['id' => 1],
            [
                'bot_token' => config('services.telegram.bot_token'),
                'chat_id' => config('services.telegram.chat_id'),
                'admin_chat_id' => config('services.telegram.admin_chat_id'),
                'webhook_url' => config('app.url') . '/api/public/telegram/webhook',
                'is_active' => true,
            ]
        );

        return response()->json([
            'data' => new TelegramConfigResource($config),
            'channels' => TelegramChannelResource::collection($config->channels),
        ]);
    }

    /**
     * Actualizar la configuración global de Telegram.
     */
    public function updateConfig(UpdateTelegramConfigRequest $request): JsonResponse
    {
        $config = TelegramConfig::firstOrCreate(['id' => 1]);
        $config->update($request->validated());

        return response()->json([
            'message' => 'Configuración de Telegram actualizada con éxito.',
            'data' => new TelegramConfigResource($config),
        ]);
    }

    // ==================== GESTIÓN DE CANALES DE TELEGRAM ====================

    /**
     * Obtener todos los canales de Telegram registrados (auto-registra Canal General Principal si está vacío).
     */
    public function getChannels(): AnonymousResourceCollection
    {
        if (TelegramChannel::count() === 0) {
            $config = TelegramConfig::firstOrCreate(['id' => 1]);
            $defaultChatId = $config->chat_id ?: config('services.telegram.chat_id');

            // Solo auto-crear el canal si existe un chat_id real configurado (no hardcodeado).
            if ($defaultChatId) {
                TelegramChannel::create([
                    'telegram_config_id' => $config->id,
                    'name'               => 'Canal General Principal',
                    'chat_id'            => $defaultChatId,
                    'module'             => 'general',
                    'description'        => 'Canal principal asignado a las notificaciones globales del sistema.',
                    'is_active'          => true,
                ]);
            }
        }

        $channels = TelegramChannel::orderBy('id', 'desc')->get();
        return TelegramChannelResource::collection($channels);
    }

    /**
     * Registrar un nuevo canal de Telegram.
     */
    public function storeChannel(StoreTelegramChannelRequest $request): JsonResponse
    {
        $config = TelegramConfig::firstOrCreate(['id' => 1]);

        $channel = TelegramChannel::create([
            'telegram_config_id' => $config->id,
            'name' => $request->input('name'),
            'chat_id' => $request->input('chat_id'),
            'module' => $request->input('module', 'general'),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Canal de Telegram registrado con éxito.',
            'data' => new TelegramChannelResource($channel),
        ], 201);
    }

    /**
     * Actualizar un canal existente.
     */
    public function updateChannel(int $id, UpdateTelegramChannelRequest $request): JsonResponse
    {
        $channel = TelegramChannel::findOrFail($id);
        $channel->update($request->validated());

        return response()->json([
            'message' => 'Canal de Telegram actualizado correctamente.',
            'data' => new TelegramChannelResource($channel),
        ]);
    }

    /**
     * Alternar estado activo/inactivo de un canal.
     */
    public function toggleChannel(int $id, ToggleTelegramCommandRequest $request): JsonResponse
    {
        $channel = TelegramChannel::findOrFail($id);
        $channel->update(['is_active' => $request->boolean('is_active')]);

        return response()->json([
            'message' => 'Estado del canal actualizado correctamente.',
            'data' => new TelegramChannelResource($channel),
        ]);
    }

    /**
     * Eliminar un canal de Telegram.
     */
    public function deleteChannel(int $id): JsonResponse
    {
        $channel = TelegramChannel::findOrFail($id);
        $channel->delete();

        return response()->json([
            'message' => 'Canal eliminado correctamente.',
        ]);
    }

    /**
     * Enviar mensaje de prueba a un canal específico.
     */
    public function testChannelMessage(int $id): JsonResponse
    {
        $channel = TelegramChannel::findOrFail($id);
        $message = "📢 *[MENSAJE DE PRUEBA]*\n\nEste es un mensaje de prueba enviado al canal *{$channel->name}* desde el ERP Farmacias.";

        $success = $this->telegramService->sendMessage($message, $channel->chat_id);

        if ($success) {
            return response()->json([
                'message' => "Mensaje de prueba enviado con éxito a '{$channel->name}'.",
            ]);
        }

        return response()->json([
            'message' => "No se pudo enviar el mensaje a '{$channel->name}'. Verifica el Bot Token y los permisos del bot en el canal.",
        ], 400);
    }

    // ==================== COMANDOS Y WEBHOOK ====================

    /**
     * Obtener comandos/mensajes por módulo con su canal asignado.
     */
    public function getModuleCommands(string $module, \App\Services\Telegram\TelegramCommandService $commandService): AnonymousResourceCollection
    {
        $commands = $commandService->getCommandsForModule($module);

        return TelegramCommandResource::collection($commands);
    }

    /**
     * Alternar el estado activo/inactivo de un comando.
     */
    public function toggleCommand(int $id, ToggleTelegramCommandRequest $request, \App\Services\Telegram\TelegramCommandService $commandService): JsonResponse
    {
        $command = $commandService->toggleCommandState($id, $request->boolean('is_active'));

        return response()->json([
            'message' => 'Estado del comando actualizado correctamente.',
            'data' => new TelegramCommandResource($command),
        ]);
    }

    /**
     * Actualizar un comando específico.
     */
    public function updateCommand(int $id, UpdateTelegramCommandRequest $request, \App\Services\Telegram\TelegramCommandService $commandService): JsonResponse
    {
        $command = $commandService->updateCommand($id, $request->validated());

        return response()->json([
            'message' => 'Comando actualizado correctamente.',
            'data' => new TelegramCommandResource($command),
        ]);
    }

    /**
     * Registrar Webhook en la API de Telegram.
     */
    public function registerWebhook(): JsonResponse
    {
        $config = TelegramConfig::firstOrCreate(['id' => 1]);

        if (empty($config->bot_token)) {
            return response()->json([
                'message' => 'El Token de Bot de Telegram no está configurado.',
            ], 422);
        }

        $webhookUrl = $config->webhook_url ?: (config('app.url') . '/api/public/telegram/webhook');

        try {
            $url = "https://api.telegram.org/bot{$config->bot_token}/setWebhook";
            $response = Http::timeout(10)
                ->retry(3, 100)
                ->post($url, [
                    'url' => $webhookUrl,
                ]);

            if ($response->successful() && ($response->json()['ok'] ?? false)) {
                $config->update(['webhook_url' => $webhookUrl]);
                return response()->json([
                    'message' => 'Webhook registrado correctamente en Telegram.',
                    'telegram_response' => $response->json(),
                ]);
            }

            return response()->json([
                'message' => 'Telegram rechazó el registro del Webhook.',
                'error' => $response->json()['description'] ?? $response->body(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Excepción al conectar con la API de Telegram: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar el estado del Webhook registrado en Telegram API.
     */
    public function getWebhookStatus(): JsonResponse
    {
        $config = TelegramConfig::firstOrCreate(['id' => 1]);

        if (empty($config->bot_token)) {
            return response()->json([
                'status' => 'unconfigured',
                'message' => 'Token no configurado',
            ]);
        }

        try {
            $url = "https://api.telegram.org/bot{$config->bot_token}/getWebhookInfo";
            $response = Http::timeout(5)->get($url);

            if ($response->successful() && ($response->json()['ok'] ?? false)) {
                return response()->json([
                    'status' => 'success',
                    'info' => $response->json()['result'] ?? [],
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $response->json()['description'] ?? 'Error al obtener información de webhook',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
