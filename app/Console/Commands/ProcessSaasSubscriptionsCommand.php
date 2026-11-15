<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Tenancy\SaasSubscriptionServiceInterface;
use Illuminate\Console\Command;

class ProcessSaasSubscriptionsCommand extends Command
{
    /**
     * Nombre y firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'saas:process-subscriptions';

    /**
     * Descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Verifica vencimientos de licencias SaaS, aplica periodos de gracia y suspende instancias en mora.';

    /**
     * Ejecutar el comando de consola.
     */
    public function handle(SaasSubscriptionServiceInterface $subscriptionService): int
    {
        $this->info('Procesando ciclo de vida de suscripciones SaaS...');

        $result = $subscriptionService->processExpirationsAndSuspensions();

        $this->info("Licencias movidas a periodo de gracia (past_due): {$result['moved_to_past_due']}");
        $this->info("Licencias suspendidas por mora (suspended): {$result['moved_to_suspended']}");

        return self::SUCCESS;
    }
}
