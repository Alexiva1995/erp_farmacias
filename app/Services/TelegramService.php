<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?string $token;
    protected ?string $chatId;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
    }

    /**
     * Enviar mensaje simple a Telegram.
     */
    public function sendMessage(string $message): bool
    {
        if (empty($this->token) || empty($this->chatId)) {
            Log::warning('[TelegramService] TELEGRAM_BOT_TOKEN o TELEGRAM_CHAT_ID no configurados en el .env');
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
            $response = Http::post($url, [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

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
}
