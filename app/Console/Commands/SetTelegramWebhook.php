<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';
    protected $description = 'Registrar el webhook del bot de Telegram en los servidores de Telegram';

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');
        
        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN no configurado en el archivo .env');
            return Command::FAILURE;
        }

        $webhookUrl = url('/api/public/telegram/webhook');

        $this->info("Registrando webhook: {$webhookUrl}");

        $response = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $webhookUrl
        ]);

        if ($response->successful() && $response->json('ok')) {
            $this->info('✅ Webhook registrado exitosamente en Telegram.');
            return Command::SUCCESS;
        }

        $this->error('❌ Error al registrar el webhook: ' . $response->body());
        return Command::FAILURE;
    }
}
