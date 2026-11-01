<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TelegramConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?string $token = null;
    protected ?string $chatId = null;
    protected ?string $adminChatId = null;
    protected bool $isActive = false;

    public function __construct()
    {
        $this->refreshConfig();
    }

    /**
     * Recargar configuración en caliente desde la base de datos de la instalación activa.
     */
    public function refreshConfig(): void
    {
        $dbConfig = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('telegram_configs')) {
                $dbConfig = TelegramConfig::first();
            }
        } catch (\Throwable $e) {
            // Manejo seguro en entorno previo a migraciones o testing
        }

        $this->token = !empty($dbConfig?->bot_token) ? trim($dbConfig->bot_token) : null;
        $this->chatId = !empty($dbConfig?->chat_id) ? trim((string) $dbConfig->chat_id) : null;
        $this->adminChatId = !empty($dbConfig?->admin_chat_id) ? trim((string) $dbConfig->admin_chat_id) : $this->chatId;
        $this->isActive = $dbConfig ? (bool) $dbConfig->is_active : false;

        // Si no hay token de bot registrado en la base de datos de esta instalación, permanece inactivo
        if (empty($this->token)) {
            $this->isActive = false;
        }
    }

    public function isGloballyActive(): bool
    {
        return $this->isActive && !empty($this->token);
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getChatId(): ?string
    {
        return $this->chatId;
    }

    public function getAdminChatId(): ?string
    {
        return $this->adminChatId;
    }

    /**
     * Enviar mensaje a Telegram con timeout y reintentos estructurados.
     */
    public function sendMessage(string $message, string|int|null $customChatId = null, ?array $replyMarkup = null): bool
    {
        if (!$this->isGloballyActive()) {
            Log::info('[TelegramService] Envío cancelado: Telegram no configurado o desactivado en esta instalación.');
            return false;
        }

        $targetChatId = $customChatId ? (string) $customChatId : $this->chatId;

        if (empty($this->token) || empty($targetChatId)) {
            Log::info('[TelegramService] Envío cancelado: Bot token o Chat ID no configurados en esta instalación.');
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
            $payload = [
                'chat_id' => $targetChatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ];

            if ($replyMarkup) {
                $payload['reply_markup'] = $replyMarkup;
            }

            $response = Http::timeout(10)
                ->retry(3, 100)
                ->post($url, $payload);

            if ($response->successful() && ($response->json()['ok'] ?? false)) {
                return true;
            }

            Log::error('[TelegramService] Error enviando mensaje Telegram: ' . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error('[TelegramService] Excepción al enviar mensaje a Telegram: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar mensaje al chat personal del administrador.
     */
    public function sendToAdmin(string $message, ?array $replyMarkup = null): bool
    {
        return $this->sendMessage($message, $this->adminChatId, $replyMarkup);
    }

    /**
     * Descargar un archivo desde Telegram y guardarlo temporalmente.
     */
    public function downloadFile(string $fileId): ?string
    {
        if (empty($this->token)) {
            Log::info('[TelegramService] Bot token no configurado en esta instalación para descarga.');
            return null;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->token}/getFile";
            $response = Http::timeout(10)->retry(3, 100)->post($url, ['file_id' => $fileId]);

            if (!$response->successful()) {
                Log::error('[TelegramService] Error obteniendo ruta de archivo: ' . $response->body());
                return null;
            }

            $filePath = $response->json()['result']['file_path'] ?? null;
            if (!$filePath) {
                Log::error('[TelegramService] No se encontró file_path en respuesta de Telegram getFile.');
                return null;
            }

            $downloadUrl = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";
            $fileContents = Http::timeout(20)->retry(3, 100)->get($downloadUrl)->body();

            $tempDir = storage_path('app/temp/telegram');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
            $localPath = $tempDir . '/' . uniqid('tg_') . '.' . $extension;
            file_put_contents($localPath, $fileContents);

            return $localPath;
        } catch (\Throwable $e) {
            Log::error('[TelegramService] Excepción descargando archivo de Telegram: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Enviar un documento (PDF, archivo) por Telegram.
     */
    public function sendDocument(string $filePath, string|int|null $customChatId = null, ?string $caption = null): bool
    {
        if (!$this->isGloballyActive()) {
            Log::info('[TelegramService] Envío de documento cancelado: Telegram no configurado o desactivado en esta instalación.');
            return false;
        }

        $targetChatId = $customChatId ? (string) $customChatId : $this->chatId;

        if (empty($this->token) || empty($targetChatId) || !file_exists($filePath)) {
            Log::info('[TelegramService] No se puede enviar documento: Token o ChatID no configurado en esta instalación, o archivo no existe.');
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->token}/sendDocument";

            $response = Http::timeout(30)
                ->retry(2, 200)
                ->attach('document', file_get_contents($filePath), basename($filePath))
                ->post($url, [
                    'chat_id' => $targetChatId,
                    'caption' => $caption ?: '',
                    'parse_mode' => 'Markdown',
                ]);

            if ($response->successful() && ($response->json()['ok'] ?? false)) {
                return true;
            }

            Log::error('[TelegramService] Error al enviar documento por Telegram: ' . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error('[TelegramService] Excepción al enviar documento a Telegram: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si un módulo de Telegram está habilitado globalmente y en la configuración.
     */
    public function isModuleEnabled(string $module): bool
    {
        if (!$this->isGloballyActive()) {
            return false;
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('telegram_configs')) {
                $config = TelegramConfig::first();
                if ($config && isset($config->is_active) && !$config->is_active) {
                    return false;
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('general_settings')) {
                $setting = \App\Models\GeneralSetting::first();
                if ($setting && is_array($setting->enabled_telegram_views)) {
                    $normalizedModule = match (strtolower($module)) {
                        'canchas' => 'alquileres',
                        'general' => 'generales',
                        default => strtolower($module),
                    };

                    return in_array($normalizedModule, $setting->enabled_telegram_views, true);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[TelegramService] Error verificando si módulo está activo: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Verificar si un comando específico está activo y habilitado.
     */
    public function isCommandActive(string $command, ?string $module = null): bool
    {
        if (!$this->isGloballyActive()) {
            return false;
        }

        if ($module && !$this->isModuleEnabled($module)) {
            return false;
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('telegram_commands')) {
                $query = \App\Models\TelegramCommand::query()->where('command', $command);
                if ($module) {
                    $normalizedModule = match (strtolower($module)) {
                        'canchas' => 'alquileres',
                        'general' => 'generales',
                        default => strtolower($module),
                    };
                    $query->where('module', $normalizedModule);
                }
                $cmd = $query->first();
                if ($cmd !== null) {
                    if (!$this->isModuleEnabled((string) $cmd->module)) {
                        return false;
                    }
                    return (bool) $cmd->is_active;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[TelegramService] Error verificando estado del comando: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Obtener el Chat ID de destino configurado para un comando específico (canal asignado o fallback).
     */
    public function getTargetChatIdForCommand(string $command, ?string $module = null): ?string
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('telegram_commands')) {
                $query = \App\Models\TelegramCommand::with('channel')->where('command', $command);
                if ($module) {
                    $normalizedModule = match (strtolower($module)) {
                        'canchas' => 'alquileres',
                        'general' => 'generales',
                        default => strtolower($module),
                    };
                    $query->where('module', $normalizedModule);
                }
                $cmd = $query->first();
                if ($cmd && $cmd->channel && $cmd->channel->is_active && !empty($cmd->channel->chat_id)) {
                    return (string) $cmd->channel->chat_id;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[TelegramService] Error obteniendo canal de comando: ' . $e->getMessage());
        }

        return $this->getChatId();
    }
}
