<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Api\Central\SaasSubscriptionController;
use App\Mail\CobroMensualidadMail;
use App\Models\SaasSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOverdueBillingEmailsCommand extends Command
{
    /**
     * Nombre y firma del comando Artisan.
     */
    protected $signature = 'saas:send-overdue-billing-emails';

    /**
     * Descripción del comando.
     */
    protected $description = 'Envía diariamente el correo de cobro a los tenants cuya suscripción se encuentra vencida.';

    public function handle(SaasSubscriptionController $controller): int
    {
        $overdueSubscriptions = SaasSubscription::with(['tenant.domains', 'plan'])
            ->whereNotNull('contact_email')
            ->where('contact_email', '!=', '')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', now()->startOfDay())
            ->get();

        if ($overdueSubscriptions->isEmpty()) {
            $this->info('No hay suscripciones vencidas con correo de contacto.');
            return self::SUCCESS;
        }

        $controller->configureDynamicMailer();
        $sent = 0;

        foreach ($overdueSubscriptions as $subscription) {
            $tenant = $subscription->tenant;
            if (!$tenant) {
                continue;
            }

            try {
                $data = $controller->buildBillingData($tenant, $subscription);
                Mail::to($subscription->contact_email)->send(new CobroMensualidadMail($data));
                $sent++;
            } catch (\Throwable $e) {
                Log::error("Error en envío diario de cobro vencido para tenant {$tenant->id}: " . $e->getMessage());
            }
        }

        $this->info("Correos de cobro vencido enviados: {$sent}.");

        return self::SUCCESS;
    }
}
