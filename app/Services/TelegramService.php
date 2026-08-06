<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TelegramConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?string $token;
    protected ?string $chatId;
    protected ?string $adminChatId;

    public function __construct()
    {
        $dbConfig = TelegramConfig::first();

        $this->token = $dbConfig?->bot_token ?: config('services.telegram.bot_token');
        $this->chatId = $dbConfig?->chat_id ?: config('services.telegram.chat_id');
        $this->adminChatId = $dbConfig?->admin_chat_id ?: (config('services.telegram.admin_chat_id') ?: $this->chatId);
    }

    /**
     * Recargar configuración en caliente.
     */
    public function refreshConfig(): void
    {
        $dbConfig = TelegramConfig::first();
        $this->token = $dbConfig?->bot_token ?: config('services.telegram.bot_token');
        $this->chatId = $dbConfig?->chat_id ?: config('services.telegram.chat_id');
        $this->adminChatId = $dbConfig?->admin_chat_id ?: (config('services.telegram.admin_chat_id') ?: $this->chatId);
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
        $targetChatId = $customChatId ? (string) $customChatId : $this->chatId;

        if (empty($this->token) || empty($targetChatId)) {
            Log::warning('[TelegramService] Bot token o Chat ID no configurados');
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
            Log::warning('[TelegramService] Bot token no configurado para descarga');
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
        $targetChatId = $customChatId ? (string) $customChatId : $this->chatId;

        if (empty($this->token) || empty($targetChatId) || !file_exists($filePath)) {
            Log::warning('[TelegramService] No se puede enviar documento: Token o ChatID no configurado, o archivo no existe.');
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
}
