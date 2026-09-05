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
    protected $description = 'Envía el reporte de pagos vencidos diariamente al administrador de Telegram';

    /**
     * Execute the console command.
     */
    public function handle(TelegramWebhookService $webhookService): int
    {

        $adminChatId = config('services.telegram.admin_chat_id') ?: config('services.telegram.chat_id');
        if (empty($adminChatId)) {
            $this->warn('TELEGRAM_ADMIN_CHAT_ID o TELEGRAM_CHAT_ID no configurados. Omitiendo envío.');
            return Command::SUCCESS;
        }

        $webhookService->sendOverduePayments($adminChatId);
        $this->info('Reporte de pagos vencidos enviado exitosamente.');
        return 0;
    }
}
