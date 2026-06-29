<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramWebhookService;
use Illuminate\Console\Command;

class SendUpcomingPaymentsTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:send-upcoming-payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía el reporte de pagos pendientes de los próximos 7 días al administrador de Telegram';

    /**
     * Execute the console command.
     */
    public function handle(TelegramWebhookService $webhookService): int
    {
        // Solo ejecutar en entornos de farmacia
        $dbName = config('database.connections.mysql.database');
        $botType = env('TELEGRAM_BOT_TYPE');
        
        $isFarmacia = str_contains($dbName, 'farmacia') || $botType === 'farmacia';

        if (!$isFarmacia) {
            $this->info('Comando omitido: Este entorno no es de farmacia.');
            return 0;
        }

        $adminChatId = config('services.telegram.admin_chat_id') ?: config('services.telegram.chat_id');
        if (empty($adminChatId)) {
            $this->error('TELEGRAM_ADMIN_CHAT_ID o TELEGRAM_CHAT_ID no configurados.');
            return 1;
        }

        $webhookService->sendUpcoming7DaysPayments($adminChatId);
        $this->info('Reporte de pagos pendientes enviado exitosamente.');
        return 0;
    }
}
