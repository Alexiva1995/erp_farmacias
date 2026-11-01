<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use App\Services\TelegramService;
use Illuminate\Contracts\Foundation\Application;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class TelegramTenancyBootstrapper implements TenancyBootstrapper
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function bootstrap(Tenant $tenant)
    {
        // Al inicializar el tenant, recargar la configuración de Telegram propia de su base de datos
        if ($this->app->bound(TelegramService::class)) {
            $this->app->make(TelegramService::class)->refreshConfig();
        }
    }

    public function revert()
    {
        // Al revertir al contexto central, recargar la configuración correspondiente
        if ($this->app->bound(TelegramService::class)) {
            $this->app->make(TelegramService::class)->refreshConfig();
        }
    }
}
