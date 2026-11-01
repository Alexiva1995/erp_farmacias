<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use App\Models\GeneralSetting;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class MailTenancyBootstrapper implements TenancyBootstrapper
{
    protected Application $app;

    /**
     * @var array<string, mixed>
     */
    protected array $originalMailConfig = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function bootstrap(Tenant $tenant)
    {
        // Guardar configuración original de mail del contexto central
        $this->originalMailConfig = [
            'default' => $this->app['config']['mail.default'] ?? 'smtp',
            'smtp' => $this->app['config']['mail.mailers.smtp'] ?? [],
            'from' => $this->app['config']['mail.from'] ?? [],
        ];

        $setting = null;
        try {
            if (Schema::hasTable('general_settings')) {
                $setting = GeneralSetting::first();
            }
        } catch (\Throwable $e) {
            // Manejo seguro previo a migraciones
        }

        if ($setting && !empty($setting->mail_host) && !empty($setting->mail_username)) {
            // Aplicar configuración SMTP propia del tenant
            $mailer = $setting->mail_mailer ?: 'smtp';
            $encryption = $setting->mail_encryption ?: 'tls';
            $fromAddress = $setting->mail_from_address ?: $setting->mail_username;
            $fromName = $setting->mail_from_name ?: ($setting->app_name ?: config('app.name'));

            $this->app['config']['mail.default'] = $mailer;
            $this->app['config']['mail.mailers.smtp.transport'] = 'smtp';
            $this->app['config']['mail.mailers.smtp.host'] = $setting->mail_host;
            $this->app['config']['mail.mailers.smtp.port'] = (int) ($setting->mail_port ?: 587);
            $this->app['config']['mail.mailers.smtp.encryption'] = $encryption === 'none' ? null : $encryption;
            $this->app['config']['mail.mailers.smtp.username'] = $setting->mail_username;
            $this->app['config']['mail.mailers.smtp.password'] = $setting->mail_password;
            $this->app['config']['mail.from.address'] = $fromAddress;
            $this->app['config']['mail.from.name'] = $fromName;
        } else {
            // Si la instalación no tiene configurado su propio correo, se desactiva el envío
            // usando el driver 'log' para evitar usar el servidor SMTP del .env global
            $this->app['config']['mail.default'] = 'log';
        }

        Mail::purge('smtp');
    }

    public function revert()
    {
        if (!empty($this->originalMailConfig)) {
            $this->app['config']['mail.default'] = $this->originalMailConfig['default'] ?? 'smtp';
            $this->app['config']['mail.mailers.smtp'] = $this->originalMailConfig['smtp'] ?? [];
            $this->app['config']['mail.from'] = $this->originalMailConfig['from'] ?? [];
            Mail::purge('smtp');
        }
    }
}
