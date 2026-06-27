<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?string $token;
    protected ?string $chatId;
    protected ?string $adminChatId;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
        $this->adminChatId = config('services.telegram.admin_chat_id') ?: $this->chatId;
    }

    /**
     * Enviar mensaje a Telegram (opcionalmente a un chat personalizado).
     */
    public function sendMessage(string $message, ?string $customChatId = null, ?array $replyMarkup = null): bool
    {
        $targetChatId = $customChatId ?: $this->chatId;

        if (empty($this->token) || empty($targetChatId)) {
            Log::warning('[TelegramService] TELEGRAM_BOT_TOKEN o CHAT_ID no configurados');
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

            $response = Http::post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            Log::error('[TelegramService] Error al enviar mensaje: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('[TelegramService] Excepción al enviar a Telegram: ' . $e->getMessage());
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
}
