<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LaboratorySyncService
{
    /**
     * Obtiene los grupos de laboratorios de la base de datos maestra (tovaerp_master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterGroupsLaboratories(): array
    {
        $masterConnection = 'master_catalog';
        if (!Schema::connection($masterConnection)->hasTable('groups_laboratories')) {
            $masterConnection = config('tenancy.database.central_connection', 'mysql');
        }

        if (!Schema::connection($masterConnection)->hasTable('groups_laboratories')) {
            return [];
        }

        return DB::connection($masterConnection)
            ->table('groups_laboratories')
            ->select(['id', 'name', 'created_at', 'updated_at'])
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();
    }

    /**
     * Obtiene los laboratorios de la base de datos maestra (tovaerp_master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterLaboratories(): array
    {
        $masterConnection = 'master_catalog';
        if (!Schema::connection($masterConnection)->hasTable('laboratories')) {
            $masterConnection = config('tenancy.database.central_connection', 'mysql');
        }

        if (!Schema::connection($masterConnection)->hasTable('laboratories')) {
            return [];
        }

        $query = DB::connection($masterConnection)->table('laboratories');
        $columns = ['id', 'name', 'group_id', 'created_at', 'updated_at'];

        if (Schema::connection($masterConnection)->hasColumn('laboratories', 'parent_id')) {
            $columns[] = 'parent_id';
        }

        return $query->select($columns)
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();
    }

    /**
     * Sincroniza los laboratorios y sus grupos hacia un tenant específico.
     *
     * @param Tenant $tenant
     * @param array<int, array<string, mixed>>|null $masterGroups
     * @param array<int, array<string, mixed>>|null $masterLabs
     * @return int Cantidad de laboratorios sincronizados
     */
    public function syncTenant(
        Tenant $tenant,
        ?array $masterGroups = null,
        ?array $masterLabs = null
    ): int {
        $groups = $masterGroups ?? $this->getMasterGroupsLaboratories();
        $labs = $masterLabs ?? $this->getMasterLaboratories();

        if (empty($labs) && empty($groups)) {
            return 0;
        }

        $tenant->run(function () use ($groups, $labs) {
            // 1. Sincronizar grupos de laboratorios primero por FK
            if (!empty($groups) && Schema::hasTable('groups_laboratories')) {
                DB::table('groups_laboratories')->upsert(
                    $groups,
                    ['id'],
                    ['name', 'updated_at']
                );
            }

            // 2. Sincronizar laboratorios deshabilitando FK temporalmente
            if (!empty($labs) && Schema::hasTable('laboratories')) {
                $hasParentId = Schema::hasColumn('laboratories', 'parent_id');
                $updateColumns = ['name', 'group_id', 'updated_at'];
                if ($hasParentId) {
                    $updateColumns[] = 'parent_id';
                }

                $filteredLabs = array_map(function ($lab) use ($hasParentId) {
                    if (!$hasParentId) {
                        unset($lab['parent_id']);
                    }
                    return $lab;
                }, $labs);

                DB::statement('SET FOREIGN_KEY_CHECKS = 0');
                try {
                    foreach (array_chunk($filteredLabs, 500) as $chunk) {
                        DB::table('laboratories')->upsert(
                            $chunk,
                            ['id'],
                            $updateColumns
                        );
                    }
                } finally {
                    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
                }
            }
        });

        return count($labs);
    }

    /**
     * Sincroniza los laboratorios del master hacia todos los tenants o uno en específico.
     *
     * @param string|null $tenantId
     * @return array<string, int> Resumen de tenants y cantidad sincronizada
     */
    public function syncAllTenants(?string $tenantId = null): array
    {
        $centralConnection = config('tenancy.database.central_connection', 'mysql');
        if (!Schema::connection($centralConnection)->hasTable('tenants')) {
            return [];
        }

        $masterGroups = $this->getMasterGroupsLaboratories();
        $masterLabs = $this->getMasterLaboratories();

        if (empty($masterLabs)) {
            return [];
        }

        $tenantQuery = Tenant::query();
        if ($tenantId !== null) {
            $tenantQuery->where('id', $tenantId);
        }

        $tenants = $tenantQuery->get();
        $results = [];

        foreach ($tenants as $tenant) {
            try {
                $count = $this->syncTenant($tenant, $masterGroups, $masterLabs);
                $results[$tenant->id] = $count;
            } catch (\Throwable $e) {
                Log::error("Error sincronizando laboratorios en tenant {$tenant->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                $results[$tenant->id] = -1;
            }
        }

        return $results;
    }

    /**
     * Fusiona uno o varios laboratorios duplicados dentro de un laboratorio principal (target)
     * reasignando productos, comisiones de empleados y vínculos de proveedores, y eliminando
     * los duplicados en tovaerp_master, la base central y TODOS los tenants.
     *
     * @param int $targetId ID del laboratorio que prevalece
     * @param array<int, int> $sourceIds IDs de los laboratorios duplicados a absorber y eliminar
     * @return array<string, mixed> Resumen de la operación
     */
    public function mergeLaboratoriesEverywhere(int $targetId, array $sourceIds): array
    {
        $sourceIds = array_values(array_unique(array_filter(
            array_map('intval', $sourceIds),
            fn (int $id) => $id > 0 && $id !== $targetId
        )));

        if (empty($sourceIds)) {
            throw new \InvalidArgumentException('Debe seleccionar al menos un laboratorio diferente al principal para fusionar.');
        }

        // 1. Obtener los datos del laboratorio principal desde la conexión activa o master_catalog
        $activeConn = DB::getDefaultConnection();
        $targetLab = null;

        foreach ([$activeConn, 'master_catalog', config('tenancy.database.central_connection', 'mysql')] as $connName) {
            try {
                if (Schema::connection($connName)->hasTable('laboratories')) {
                    $found = DB::connection($connName)->table('laboratories')->where('id', $targetId)->first();
                    if ($found) {
                        $targetLab = (array) $found;
                        break;
                    }
                }
            } catch (\Throwable) {
                // Continuar probando en las siguientes conexiones
            }
        }

        if (!$targetLab) {
            throw new \RuntimeException("El laboratorio principal (#{$targetId}) no fue encontrado.");
        }

        $processedDatabases = [];
        $totalProductsReassigned = 0;
        $totalDatabasesUpdated = 0;

        // Helper para ejecutar la fusión sobre una conexión específica evitando repetir la misma base de datos física
        $runOnConnection = function (string $connName, string $label) use (
            $targetId,
            $sourceIds,
            $targetLab,
            &$processedDatabases,
            &$totalProductsReassigned,
            &$totalDatabasesUpdated
        ): void {
            try {
                $dbName = (string) DB::connection($connName)->getDatabaseName();
                if ($dbName !== '' && isset($processedDatabases[$dbName])) {
                    return;
                }

                if (!Schema::connection($connName)->hasTable('laboratories')) {
                    return;
                }

                $stats = $this->mergeInSingleConnection($connName, $targetId, $sourceIds, $targetLab);
                if ($dbName !== '') {
                    $processedDatabases[$dbName] = $stats;
                }
                $totalProductsReassigned += $stats['products_updated'];
                $totalDatabasesUpdated++;
            } catch (\Throwable $e) {
                Log::warning("Aviso al fusionar laboratorios en {$label} ({$connName}): " . $e->getMessage());
            }
        };

        // 2. Ejecutar en conexión activa, master_catalog y central
        $runOnConnection($activeConn, 'Activa');
        $runOnConnection('master_catalog', 'Master Catalog');
        $centralConn = config('tenancy.database.central_connection', 'mysql');
        $runOnConnection($centralConn, 'Central');

        // 3. Ejecutar en bases hermanas del servidor (farmaciabs_erp2 y tovaerp_zorca) si están presentes
        $siblingConfigs = [
            'farmaciabs_erp2' => '/home/farmaciabs/public_html/erp3/.env',
            'tovaerp_zorca'   => '/home/tovaerp/zorcasalud.tovaerp.com/.env',
        ];

        $tenantIds = [];
        try {
            if (Schema::connection($centralConn)->hasTable('tenants')) {
                $tenantIds = DB::connection($centralConn)->table('tenants')->pluck('id')->map(fn ($id) => (string) $id)->toArray();
            }
        } catch (\Throwable) {
        }

        foreach ($siblingConfigs as $siblingDb => $envPath) {
            if (isset($processedDatabases[$siblingDb])) {
                continue;
            }

            $connKey = 'merge_ext_' . $siblingDb;
            if ($this->configureExternalDatabaseConnection($connKey, $siblingDb, $envPath, $centralConn)) {
                $runOnConnection($connKey, $siblingDb);

                // Si aún no teníamos la lista de tenants (ej. ejecutado desde farmaciabs.com), leerla de tovaerp_zorca
                if (empty($tenantIds)) {
                    try {
                        if (Schema::connection($connKey)->hasTable('tenants')) {
                            $tenantIds = DB::connection($connKey)->table('tenants')->pluck('id')->map(fn ($id) => (string) $id)->toArray();
                        }
                    } catch (\Throwable) {
                    }
                }
            }
        }

        // 4. Ejecutar en TODOS los tenants registrados
        $tenantPrefix = (string) config('tenancy.database.prefix', 'tovaerp_tenant_');
        $tenantSuffix = (string) config('tenancy.database.suffix', '');

        foreach (array_unique($tenantIds) as $tenantIdStr) {
            $tenantDbName = $tenantPrefix . $tenantIdStr . $tenantSuffix;
            if (isset($processedDatabases[$tenantDbName])) {
                continue;
            }

            $tenantConnKey = 'merge_tenant_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $tenantIdStr);
            if (
                $this->configureExternalDatabaseConnection(
                    $tenantConnKey,
                    $tenantDbName,
                    '/home/tovaerp/zorcasalud.tovaerp.com/.env',
                    $centralConn
                )
            ) {
                $runOnConnection($tenantConnKey, "Tenant {$tenantIdStr}");
                DB::purge($tenantConnKey);
            }
        }

        // 5. Limpiar cachés de laboratorios
        try {
            Cache::forget('resources.laboratories');
            Cache::forget('resources.laboratories.mysql');
            Cache::forget('resources.laboratories.master_catalog');
            Cache::forget('resources.laboratories.tenant');
        } catch (\Throwable) {
        }

        return [
            'target_laboratory_id' => $targetId,
            'target_laboratory_name' => $targetLab['name'] ?? '',
            'merged_source_ids' => $sourceIds,
            'databases_updated' => $totalDatabasesUpdated,
            'products_reassigned' => $totalProductsReassigned,
        ];
    }

    /**
     * Realiza la reasignación de llaves foráneas y eliminación de laboratorios duplicados en una conexión dada.
     *
     * @param string $conn
     * @param int $targetId
     * @param array<int, int> $sourceIds
     * @param array<string, mixed> $targetLab
     * @return array{products_updated: int, labs_deleted: int}
     */
    protected function mergeInSingleConnection(
        string $conn,
        int $targetId,
        array $sourceIds,
        array $targetLab
    ): array {
        $productsUpdated = 0;
        $labsDeleted = 0;

        DB::connection($conn)->statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            // 1. Asegurar que el laboratorio principal exista en esta conexión con su mismo ID
            $existsTarget = DB::connection($conn)->table('laboratories')->where('id', $targetId)->exists();
            if (!$existsTarget) {
                $insertData = [
                    'id' => $targetId,
                    'name' => $targetLab['name'],
                    'group_id' => $targetLab['group_id'] ?? null,
                    'created_at' => $targetLab['created_at'] ?? now(),
                    'updated_at' => now(),
                ];
                if (Schema::connection($conn)->hasColumn('laboratories', 'parent_id')) {
                    $insertData['parent_id'] = $targetLab['parent_id'] ?? null;
                }
                DB::connection($conn)->table('laboratories')->insert($insertData);
            } elseif (empty($targetLab['group_id'])) {
                // Si el laboratorio destino no tenía grupo asignado pero alguno de los absorbidos sí tiene grupo, heredarlo
                $donorWithGroup = DB::connection($conn)->table('laboratories')
                    ->whereIn('id', $sourceIds)
                    ->whereNotNull('group_id')
                    ->first();
                if ($donorWithGroup && !empty($donorWithGroup->group_id)) {
                    DB::connection($conn)->table('laboratories')
                        ->where('id', $targetId)
                        ->whereNull('group_id')
                        ->update([
                            'group_id' => $donorWithGroup->group_id,
                            'updated_at' => now(),
                        ]);
                }
            }

            // 2. Reasignar productos al laboratorio principal
            if (
                Schema::connection($conn)->hasTable('products')
                && Schema::connection($conn)->hasColumn('products', 'laboratory_id')
            ) {
                $productsUpdated = DB::connection($conn)->table('products')
                    ->whereIn('laboratory_id', $sourceIds)
                    ->update(['laboratory_id' => $targetId]);
            }

            $inList = implode(',', array_map('intval', $sourceIds));

            // 3. Reasignar relaciones con empleados (employee_laboratory)
            if (
                Schema::connection($conn)->hasTable('employee_laboratory')
                && Schema::connection($conn)->hasColumn('employee_laboratory', 'laboratory_id')
            ) {
                DB::connection($conn)->statement(
                    "UPDATE IGNORE `employee_laboratory` SET `laboratory_id` = ? WHERE `laboratory_id` IN ({$inList})",
                    [$targetId]
                );
                DB::connection($conn)->table('employee_laboratory')
                    ->whereIn('laboratory_id', $sourceIds)
                    ->delete();
            }

            // 4. Reasignar relaciones con proveedores (supplier_laboratories)
            if (
                Schema::connection($conn)->hasTable('supplier_laboratories')
                && Schema::connection($conn)->hasColumn('supplier_laboratories', 'laboratory_id')
            ) {
                DB::connection($conn)->statement(
                    "UPDATE IGNORE `supplier_laboratories` SET `laboratory_id` = ? WHERE `laboratory_id` IN ({$inList})",
                    [$targetId]
                );
                DB::connection($conn)->table('supplier_laboratories')
                    ->whereIn('laboratory_id', $sourceIds)
                    ->delete();
            }

            // 5. Reasignar parent_id si la tabla laboratories maneja jerarquía
            if (Schema::connection($conn)->hasColumn('laboratories', 'parent_id')) {
                DB::connection($conn)->table('laboratories')
                    ->whereIn('parent_id', $sourceIds)
                    ->update(['parent_id' => $targetId]);
            }

            // 6. Eliminar definitivamente los laboratorios absorbidos
            $labsDeleted = DB::connection($conn)->table('laboratories')
                ->whereIn('id', $sourceIds)
                ->delete();
        } finally {
            DB::connection($conn)->statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return [
            'products_updated' => $productsUpdated,
            'labs_deleted' => $labsDeleted,
        ];
    }

    /**
     * Configura y verifica una conexión externa hacia otra base de datos del mismo servidor.
     */
    protected function configureExternalDatabaseConnection(
        string $connKey,
        string $databaseName,
        string $fallbackEnvPath,
        string $baseConn
    ): bool {
        $baseConfig = config("database.connections.{$baseConn}") ?? config('database.connections.mysql');
        if (!is_array($baseConfig)) {
            return false;
        }

        // Intentar primero con las credenciales actuales
        $cfg = $baseConfig;
        $cfg['database'] = $databaseName;
        config(["database.connections.{$connKey}" => $cfg]);
        DB::purge($connKey);

        try {
            DB::connection($connKey)->getPdo();
            return true;
        } catch (\Throwable) {
            // Intentar leyendo credenciales del .env hermano
            if (is_readable($fallbackEnvPath)) {
                $user = null;
                $pass = null;
                $lines = file($fallbackEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), 'DB_USERNAME=')) {
                        $user = trim(explode('=', $line, 2)[1] ?? '', " \t\n\r\0\x0B\"'");
                    }
                    if (str_starts_with(trim($line), 'DB_PASSWORD=')) {
                        $pass = trim(explode('=', $line, 2)[1] ?? '', " \t\n\r\0\x0B\"'");
                    }
                }

                if ($user) {
                    try {
                        $cfg['username'] = $user;
                        $cfg['password'] = $pass ?? '';
                        config(["database.connections.{$connKey}" => $cfg]);
                        DB::purge($connKey);
                        DB::connection($connKey)->getPdo();
                        return true;
                    } catch (\Throwable) {
                    }
                }
            }
        }

        return false;
    }
}
