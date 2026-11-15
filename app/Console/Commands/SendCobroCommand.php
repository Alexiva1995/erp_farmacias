<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Tenancy\SaasSubscriptionServiceInterface;
use App\Mail\CobroMensualidadMail;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendCobroCommand extends Command
{
    /**
     * Nombre y firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'tova:send-cobro {email : El correo de destino} {--tenant= : ID del tenant para cargar plan, precio y vencimiento} {--rate=3400 : Tasa de cambio COP}';

    /**
     * Descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Enviar correo de cobro de mensualidad a un cliente';

    /**
     * Ejecutar el comando de consola.
     */
    public function handle(SaasSubscriptionServiceInterface $subscriptionService): int
    {
        $recipient = (string) $this->argument('email');
        $tenantId = $this->option('tenant');
        $rate = (float) ($this->option('rate') ?: 3400);

        $usdAmount = 49.0;
        $planName = 'Plan PRO';
        $siteName = 'tovaerp.com';
        $siteUrl = 'https://tovaerp.com/';
        $dueDate = Carbon::today()->addMonth();

        if (!empty($tenantId)) {
            $tenant = Tenant::with('domains')->find((string) $tenantId);
            if ($tenant) {
                $subscription = $subscriptionService->syncTenantSubscription($tenant);
                $usdAmount = (float) ($subscription->price_agreed_usd ?: $tenant->monthly_price ?: 29.0);
                $planName = $subscription->plan?->name ?? strtoupper((string) ($subscription->plan_code ?: 'basic'));
                $domain = $tenant->domains->first()?->domain ?? "{$tenant->id}.tovaerp.com";
                $siteName = $domain;
                $siteUrl = "https://{$domain}/";
                if ($subscription->current_period_ends_at) {
                    $dueDate = Carbon::parse($subscription->current_period_ends_at);
                }
            }
        }

        $periodStart = (clone $dueDate)->subMonth()->format('d/m/Y');
        $periodEnd = $dueDate->format('d/m/Y');

        $data = [
            'site_url' => $siteUrl,
            'site_name' => $siteName,
            'plan_name' => $planName,
            'period' => "{$periodStart} al {$periodEnd}",
            'usd_amount' => $usdAmount,
            'rate' => $rate,
            'cop_amount' => $usdAmount * $rate,
            'banco_cuenta' => '91201530453',
            'banco_titular' => 'Estefanny Torrado',
            'banco_cc' => '1148456331',
            'binance_email' => 'Alexisjose95@gmail.com',
        ];

        $this->info("Enviando correo de cobro a: {$recipient}...");

        Mail::to($recipient)->send(new CobroMensualidadMail($data));

        $this->info('¡Correo enviado con éxito!');

        return self::SUCCESS;
    }
}
