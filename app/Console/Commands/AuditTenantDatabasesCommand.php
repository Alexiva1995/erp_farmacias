<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditTenantDatabasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:audit-databases 
                            {--drop-orphans : Eliminar interactivamente las bases de datos huérfanas no registradas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audita bases de datos en MySQL y las compara contra la tabla central de tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Analizando bases de datos en MySQL y registros de tenants...');

        try {
            $prefix = (string) config('tenancy.database.prefix', 'tovaerp_tenant_');

            // 1. Obtener todas las bases de datos del servidor MySQL
            $rawDatabases = collect(DB::select('SHOW DATABASES'))->pluck('Database')->values();

            // Filtrar bases de datos sospechosas de tenants (por prefijo o convención)
            $potentialTenantDatabases = $rawDatabases->filter(function (string $dbName) use ($prefix): bool {
                return str_starts_with($dbName, $prefix) || str_contains($dbName, 'tenant');
            })->values();

            // 2. Obtener tenants registrados en la base central
            $registeredTenants = Schema::hasTable('tenants')
                ? Tenant::with('domains')->get()
                : collect();

            // Mapear tenants registrados a su nombre de BD esperado
            $tenantDbMap = [];
            foreach ($registeredTenants as $tenant) {
                /** @var Tenant $tenant */
                $expectedDb = $tenant->database()->getName();
                $tenantDbMap[$expectedDb] = [
                    'tenant_id' => $tenant->id,
                    'domains' => $tenant->domains->pluck('domain')->implode(', ') ?: 'Sin dominio',
                    'tenant' => $tenant,
                ];
            }

            // 3. Cruzar datos
            $rows = [];
            $orphanDatabases = [];

            // Revisar bases de datos físicas encontradas en MySQL
            foreach ($potentialTenantDatabases as $dbName) {
                if (isset($tenantDbMap[$dbName])) {
                    $rows[] = [
                        'database' => $dbName,
                        'tenant_id' => $tenantDbMap[$dbName]['tenant_id'],
                        'domains' => $tenantDbMap[$dbName]['domains'],
                        'status' => '✅ VINCULADA (Visible en SaaS)',
                    ];
                    unset($tenantDbMap[$dbName]);
                } else {
                    $orphanDatabases[] = $dbName;
                    $rows[] = [
                        'database' => $dbName,
                        'tenant_id' => '--- (No registrado)',
                        'domains' => '---',
                        'status' => '⚠️ HUÉRFANA (No visible en SaaS)',
                    ];
                }
            }

            // Tenants registrados cuya base de datos no existe físicamente
            foreach ($tenantDbMap as $missingDb => $data) {
                $rows[] = [
                    'database' => "{$missingDb} (NO EXISTE)",
                    'tenant_id' => $data['tenant_id'],
                    'domains' => $data['domains'],
                    'status' => '❌ REGISTRO ROTO (Sin BD física)',
                ];
            }

            $this->newLine();
            $this->table(['Base de Datos en MySQL', 'Tenant ID', 'Dominios', 'Estado'], $rows);
            $this->newLine();

            // 4. Reporte y opciones de eliminación de huérfanas
            if (empty($orphanDatabases)) {
                $this->info('✨ No se encontraron bases de datos huérfanas.');
                return self::SUCCESS;
            }

            $this->warn("Se encontraron " . count($orphanDatabases) . " base(s) de datos huérfana(s) que consumen espacio y no están vinculadas.");

            if ($this->option('drop-orphans')) {
                if ($this->confirm('¿Deseas proceder a eliminar PERMANENTEMENTE las bases de datos huérfanas listadas?')) {
                    foreach ($orphanDatabases as $orphanDb) {
                        $this->warn("Borrando base de datos: `{$orphanDb}`...");
                        DB::statement("DROP DATABASE `{$orphanDb}`");
                        $this->info("✔ Base de datos `{$orphanDb}` eliminada.");
                    }
                    $this->info('Proceso de limpieza finalizado.');
                } else {
                    $this->line('Operación cancelada. No se borró ninguna base de datos.');
                }
            } else {
                $this->line('Para eliminarlas de forma interactiva ejecuta:');
                $this->comment('php artisan tenant:audit-databases --drop-orphans');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error ejecutando auditoría: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
