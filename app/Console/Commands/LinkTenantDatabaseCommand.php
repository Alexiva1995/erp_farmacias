<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

class LinkTenantDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:link 
                            {id : Identificador del tenant (ej. zorcasalud)} 
                            {database : Nombre exacto de la base de datos existente en MySQL (ej. tovaerp_zorca)} 
                            {--name= : Nombre comercial de la empresa} 
                            {--domain= : Dominio o subdominio asignado (ej. zorcasalud.tovaerp.com)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vincula una base de datos existente en MySQL a un tenant para que aparezca en el panel SaaS';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = strtolower(trim((string) $this->argument('id')));
        $dbName = trim((string) $this->argument('database'));
        $companyName = $this->option('name') ?: ucfirst($tenantId);
        $rawDomain = $this->option('domain') ?: "{$tenantId}.tovaerp.com";
        $domain = str_contains((string) $rawDomain, '.') ? (string) $rawDomain : "{$rawDomain}.tovaerp.com";

        $this->info("🔗 Vinculando base de datos `{$dbName}` al tenant `{$tenantId}`...");

        try {
            // 1. Crear o actualizar el Tenant en la base central sin disparar jobs de creación de base de datos
            /** @var Tenant $tenant */
            $tenant = Tenant::withoutEvents(function () use ($tenantId, $companyName, $dbName) {
                return Tenant::updateOrCreate(
                    ['id' => $tenantId],
                    [
                        'company_name' => $companyName,
                        'tenancy_db_name' => $dbName,
                        'vertical' => \App\Enums\BusinessVerticalEnum::PHARMACY->value,
                    ]
                );
            });

            // 2. Asociar el dominio
            Domain::updateOrCreate(
                ['tenant_id' => $tenantId],
                ['domain' => $domain]
            );

            $this->newLine();
            $this->info("✅ Tenant vinculado exitosamente y visible en el panel SaaS.");
            $this->table(
                ['Parámetro', 'Valor'],
                [
                    ['Tenant ID', $tenant->id],
                    ['Empresa', $companyName],
                    ['Base de Datos Física', $dbName],
                    ['Dominio de Acceso', $domain],
                ]
            );

            // 3. Ejecutar migraciones pendientes si existen
            $this->newLine();
            if ($this->confirm('¿Deseas correr las migraciones pendientes del ERP sobre esta base de datos ahora?', true)) {
                $this->call('tenants:migrate', ['--tenants' => [$tenantId]]);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Error vinculando el tenant: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
