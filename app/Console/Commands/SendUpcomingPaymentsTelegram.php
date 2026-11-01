<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Telegram\TelegramWebhookService;

class SendUpcomingPaymentsTelegram extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'telegram:send-upcoming-payments';

    /**
     * Descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Envía el reporte de pagos vencidos diariamente al administrador de Telegram';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(TelegramWebhookService $webhookService): int
    {
        $telegram = resolve(\App\Services\TelegramService::class);
        $adminChatId = $telegram->getAdminChatId() ?: $telegram->getChatId();

        if (empty($adminChatId) || empty($telegram->getToken())) {
            $this->warn('Telegram no configurado en esta instalación. Omitiendo envío.');
            return Command::SUCCESS;
        }

        if (!$telegram->isCommandActive('/pagos_pendientes', 'farmacia')) {
            $this->info('Comando omitido: El módulo de farmacia o el comando /pagos_pendientes está desactivado en Telegram.');
            return Command::SUCCESS;
        }

        $webhookService->sendOverduePayments($adminChatId);
        $this->info('Reporte de pagos vencidos enviado exitosamente.');
        return 0;
    }
}
