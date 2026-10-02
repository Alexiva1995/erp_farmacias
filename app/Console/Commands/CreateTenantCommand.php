<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Console\Command;

class CreateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create 
                            {id : Identificador y subdominio único (ej. milremedios)}
                            {--name= : Nombre comercial de la farmacia}
                            {--email=admin@example.com : Correo del administrador inicial}
                            {--password=password123 : Contraseña inicial}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un nuevo Tenant con su base de datos, subdominio y usuario administrador';

    /**
     * Execute the console command.
     */
    public function handle(TenantProvisioningService $provisioningService): int
    {
        $tenantId = strtolower(trim((string) $this->argument('id')));
        $companyName = $this->option('name') ?: ucfirst($tenantId);
        $adminEmail = (string) $this->option('email');
        $password = (string) $this->option('password');

        $this->info("🚀 Iniciando aprovisionamiento para: {$companyName} ({$tenantId}.tovaerp.com)...");

        try {
            $tenant = $provisioningService->createTenant([
                'tenant_id' => $tenantId,
                'company_name' => $companyName,
                'domain' => $tenantId,
                'admin_name' => "Admin {$companyName}",
                'admin_email' => $adminEmail,
                'password' => $password,
            ]);

            $this->newLine();
            $this->info("✅ Tenant creado exitosamente.");
            $this->table(
                ['Parámetro', 'Valor'],
                [
                    ['Tenant ID', $tenant->id],
                    ['Subdominio', "{$tenantId}.tovaerp.com"],
                    ['Base de Datos', config('tenancy.database.prefix') . $tenantId],
                    ['Admin Email', $adminEmail],
                    ['Admin Password', $password],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Error al crear el tenant: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
