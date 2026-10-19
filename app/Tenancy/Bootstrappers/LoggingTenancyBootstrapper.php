<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use Illuminate\Contracts\Foundation\Application;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class LoggingTenancyBootstrapper implements TenancyBootstrapper
{
    /** @var Application */
    protected $app;

    /** @var array<string, mixed> */
    protected array $originalPaths = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function bootstrap(Tenant $tenant)
    {
        $tenantLogDir = $this->app->storagePath('logs');

        // Asegurar que el directorio de logs del tenant exista con permisos adecuados
        if (!is_dir($tenantLogDir)) {
            @mkdir($tenantLogDir, 0755, true);
        }

        $tenantLogFile = $tenantLogDir . DIRECTORY_SEPARATOR . 'laravel.log';

        // Guardar las rutas originales del contexto central
        $this->originalPaths = [
            'single' => $this->app['config']['logging.channels.single.path'] ?? null,
            'daily' => $this->app['config']['logging.channels.daily.path'] ?? null,
            'emergency' => $this->app['config']['logging.channels.emergency.path'] ?? null,
        ];

        // Reconfigurar los canales para apuntar al archivo de log del tenant
        $this->app['config']['logging.channels.single.path'] = $tenantLogFile;
        $this->app['config']['logging.channels.daily.path'] = $tenantLogFile;
        $this->app['config']['logging.channels.emergency.path'] = $tenantLogFile;

        // Purgar los canales cacheados para que Monolog abra el nuevo stream hacia el tenant
        $this->purgeLogChannels();
    }

    public function revert()
    {
        // Restaurar rutas originales
        foreach ($this->originalPaths as $channel => $path) {
            if ($path !== null) {
                $this->app['config']["logging.channels.{$channel}.path"] = $path;
            }
        }

        $this->purgeLogChannels();
    }

    protected function purgeLogChannels(): void
    {
        if ($this->app->bound('log')) {
            $logManager = $this->app['log'];
            if (method_exists($logManager, 'forgetChannel')) {
                $logManager->forgetChannel('single');
                $logManager->forgetChannel('daily');
                $logManager->forgetChannel('emergency');
                $logManager->forgetChannel('stack');
            }
        }
    }
}
