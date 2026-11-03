<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Models\Domain;

class AuditTenantDatabasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:audit-databases 
                            {--drop-orphans : Eliminar interactivamente las bases de datos huérfanas no registradas}
                            {--purge-except= : Lista separada por comas de IDs de tenants a conservar (ej: vickmedicals,alkosto,ensalud,mil-remedios,zorcasalud). Todo lo demás se depurará}';

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

            // 2. Obtener tenants registrados en la base central
            $registeredTenants = Schema::hasTable('tenants')
                ? Tenant::with('domains')->get()
                : collect();

            // Mapear tenants registrados a su nombre de BD esperado
            $tenantDbMap = [];
            $expectedDbNames = [];
            foreach ($registeredTenants as $tenant) {
                /** @var Tenant $tenant */
                $expectedDb = $tenant->database()->getName();
                $expectedDbNames[] = strtolower($expectedDb);
                $tenantDbMap[$expectedDb] = [
                    'tenant_id' => $tenant->id,
                    'domains' => $tenant->domains->pluck('domain')->implode(', ') ?: 'Sin dominio',
                    'tenant' => $tenant,
                ];
            }

            // Filtrar bases de datos sospechosas de tenants:
            // que empiecen con tovaerp_, que contengan tenant, o que coincidan con la BD de un tenant registrado
            $potentialTenantDatabases = $rawDatabases->filter(function (string $dbName) use ($prefix, $expectedDbNames): bool {
                $lower = strtolower($dbName);
                return str_starts_with($lower, 'tovaerp_')
                    || str_starts_with($lower, strtolower($prefix))
                    || str_contains($lower, 'tenant')
                    || in_array($lower, $expectedDbNames, true);
            })->values();

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
                        'status' => '✅ VINCULADA (En tabla tenants y en MySQL)',
                    ];
                    unset($tenantDbMap[$dbName]);
                } else {
                    $orphanDatabases[] = $dbName;
                    $rows[] = [
                        'database' => $dbName,
                        'tenant_id' => '--- (No registrado)',
                        'domains' => '---',
                        'status' => '⚠️ HUÉRFANA (En MySQL, sin registro central)',
                    ];
                }
            }

            // Tenants registrados cuya base de datos no existe físicamente
            foreach ($tenantDbMap as $missingDb => $data) {
                $existsInRaw = $rawDatabases->contains(fn($d) => strcasecmp($d, $missingDb) === 0);
                if ($existsInRaw) {
                    $rows[] = [
                        'database' => $missingDb,
                        'tenant_id' => $data['tenant_id'],
                        'domains' => $data['domains'],
                        'status' => '✅ VINCULADA (En tabla tenants y en MySQL)',
                    ];
                } else {
                    $rows[] = [
                        'database' => "{$missingDb} (NO EXISTE)",
                        'tenant_id' => $data['tenant_id'],
                        'domains' => $data['domains'],
                        'status' => '❌ REGISTRO ROTO (En tabla tenants, sin BD física)',
                    ];
                }
            }

            $this->newLine();
            $this->table(['Base de Datos en MySQL', 'Tenant ID', 'Dominios', 'Estado'], $rows);
            $this->newLine();

            // 4. Si se especificó la opción --purge-except
            if ($this->option('purge-except')) {
                return $this->handlePurgeExcept(
                    (string) $this->option('purge-except'),
                    $potentialTenantDatabases,
                    $registeredTenants,
                    $prefix
                );
            }

            // 5. Reporte y opciones de eliminación de huérfanas estándar
            if (!empty($orphanDatabases)) {
                $this->warn("Se encontraron " . count($orphanDatabases) . " base(s) de datos huérfana(s) no registradas.");

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
                    $this->line('Para eliminar las huérfanas interactivamente ejecuta:');
                    $this->comment('php artisan tenant:audit-databases --drop-orphans');
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error ejecutando auditoría: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Purga controlada conservando únicamente los tenants legítimos especificados.
     *
     * @param string $purgeExceptStr
     * @param \Illuminate\Support\Collection<int, string> $potentialTenantDatabases
     * @param \Illuminate\Support\Collection<int, Tenant> $registeredTenants
     * @param string $prefix
     * @return int
     */
    protected function handlePurgeExcept(
        string $purgeExceptStr,
        $potentialTenantDatabases,
        $registeredTenants,
        string $prefix
    ): int {
        $keepTenantIds = collect(explode(',', $purgeExceptStr))
            ->map(fn($id) => trim(strtolower($id)))
            ->filter()
            ->values()
            ->all();

        if (empty($keepTenantIds)) {
            $this->error('Debes especificar al menos un tenant ID válido en --purge-except.');
            return self::FAILURE;
        }

        $this->alert("MODO DEPURACIÓN ACTIVADO (PURGE)");
        $this->info("Empresas a CONSERVAR: " . implode(', ', $keepTenantIds));

        // Determinar bases de datos a conservar
        $keepDbNames = collect($keepTenantIds)->map(function ($id) use ($prefix, $registeredTenants) {
            $tenant = $registeredTenants->firstWhere('id', $id);
            return strtolower($tenant ? $tenant->database()->getName() : "{$prefix}{$id}");
        })->all();

        // Determinar tenants a borrar de la base central
        $tenantsToPurge = $registeredTenants->reject(function ($tenant) use ($keepTenantIds) {
            return in_array(strtolower($tenant->id), $keepTenantIds, true);
        });

        // Determinar bases de datos de MySQL a borrar
        $databasesToPurge = $potentialTenantDatabases->reject(function ($dbName) use ($keepDbNames) {
            return in_array(strtolower($dbName), $keepDbNames, true);
        })->values();

        $this->newLine();
        $this->warn("Elementos que serán ELIMINADOS PERMANENTEMENTE:");
        $purgeTable = [];

        foreach ($tenantsToPurge as $t) {
            $dbName = $t->database()->getName();
            $purgeTable[] = [
                'Tipo' => 'Tenant Central + BD',
                'ID / Nombre' => $t->id,
                'Detalle' => "BD: {$dbName} (se eliminará registro y base de datos física)",
            ];
        }

        foreach ($databasesToPurge as $db) {
            if (!$tenantsToPurge->contains(fn($t) => strcasecmp($t->database()->getName(), $db) === 0)) {
                $purgeTable[] = [
                    'Tipo' => 'Base de datos huérfana',
                    'ID / Nombre' => '---',
                    'Detalle' => "BD: {$db} (se eliminará base de datos física)",
                ];
            }
        }

        if (empty($purgeTable)) {
            $this->info('No hay elementos sobrantes para depurar.');
            return self::SUCCESS;
        }

        $this->table(['Tipo', 'ID / Nombre', 'Detalle'], $purgeTable);
        $this->newLine();

        if (!$this->confirm('¿Estás COMPLETAMENTE SEGURO de eliminar todos los elementos listados arriba?')) {
            $this->line('Operación cancelada por el usuario. No se modificó nada.');
            return self::SUCCESS;
        }

        // 1. Eliminar dominios y registros centrales
        $tenantIdsToDelete = $tenantsToPurge->pluck('id')->all();
        if (!empty($tenantIdsToDelete)) {
            Domain::whereIn('tenant_id', $tenantIdsToDelete)->delete();
            Tenant::whereIn('id', $tenantIdsToDelete)->delete();
            $this->info("✔ " . count($tenantIdsToDelete) . " registro(s) de tenants eliminados de la base central.");
        }

        // 2. Eliminar bases de datos físicas de MySQL
        foreach ($databasesToPurge as $db) {
            try {
                $this->warn("Borrando base de datos `{$db}` en MySQL...");
                DB::statement("DROP DATABASE `{$db}`");
                $this->info("✔ Base de datos `{$db}` eliminada.");
            } catch (\Throwable $e) {
                $this->error("No se pudo borrar `{$db}`: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✅ Depuración completada exitosamente. Solo permanecen activas las empresas indicadas.");
        return self::SUCCESS;
    }
}
