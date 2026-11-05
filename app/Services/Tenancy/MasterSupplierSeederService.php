<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MasterSupplierSeederService
{
    public function __construct(
        protected SupplierSyncService $supplierSyncService
    ) {}

    /**
     * Resuelve y garantiza la conexión a la base de datos Master (tovaerp_master).
     */
    public function ensureMasterDatabaseAndTables(): string
    {
        $masterConn = 'master_catalog';
        $centralConn = config('tenancy.database.central_connection', 'mysql');

        try {
            DB::connection($masterConn)->getPdo();
        } catch (\Throwable $e) {
            $masterDbName = config('database.connections.master_catalog.database', 'tovaerp_master');
            DB::connection($centralConn)->statement("CREATE DATABASE IF NOT EXISTS `{$masterDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            DB::purge($masterConn);
            DB::connection($masterConn)->getPdo();
        }

        // Crear tablas en master si no existen copiando estructura de central
        foreach (['suppliers', 'supplier_connections'] as $table) {
            if (Schema::connection($centralConn)->hasTable($table) && !Schema::connection($masterConn)->hasTable($table)) {
                $createSql = DB::connection($centralConn)->select("SHOW CREATE TABLE `{$table}`");
                $prop = 'Create Table';
                $ddl = $createSql[0]->$prop;

                DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::connection($masterConn)->statement($ddl);
                DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }

        return $masterConn;
    }

    /**
     * Sincroniza proveedores y estructuras desde un tenant de referencia (ej: Barrio Sucre) hacia el Master.
     *
     * @param string|null $sourceTenantId ID o coincidencia de nombre del tenant fuente (null = conexión activa/central)
     * @param bool $overwriteMasterStructures Si es true, sobrescribe las estructuras en el Master con las del tenant
     * @return array<string, int> Estadísticas del proceso
     */
    public function syncMasterFromTenant(?string $sourceTenantId = null, bool $overwriteMasterStructures = true): array
    {
        $masterConn = $this->ensureMasterDatabaseAndTables();

        $stats = [
            'suppliers_synced' => 0,
            'connections_synced' => 0,
        ];

        // Definir closure para procesar la extracción desde el contexto del tenant o central
        $extractor = function () use ($masterConn, $overwriteMasterStructures, &$stats) {
            if (!Schema::hasTable('suppliers') || !Schema::hasTable('supplier_connections')) {
                return;
            }

            $sourceSuppliers = DB::table('suppliers')->orderBy('id')->get();
            $masterSupCols = array_flip(Schema::connection($masterConn)->getColumnListing('suppliers'));
            $masterConnCols = array_flip(Schema::connection($masterConn)->getColumnListing('supplier_connections'));

            // 1. Sincronizar Proveedores hacia tovaerp_master
            $idMapping = []; // [source_id => master_id]

            foreach ($sourceSuppliers as $sup) {
                $cleanRif = !empty($sup->rif) ? strtoupper(trim((string) $sup->rif)) : null;

                // Buscar si ya existe en el Master por RIF o por ID
                $masterSup = null;
                if ($cleanRif) {
                    $masterSup = DB::connection($masterConn)->table('suppliers')
                        ->where('rif', $cleanRif)
                        ->first();
                }

                if (!$masterSup) {
                    $masterSup = DB::connection($masterConn)->table('suppliers')
                        ->where('id', $sup->id)
                        ->first();
                }

                $payload = array_intersect_key((array) $sup, $masterSupCols);
                unset($payload['provider_tenant_id']);

                if ($masterSup) {
                    $updateCols = ['name', 'social_reason', 'rif', 'type', 'updated_at'];
                    $updatePayload = array_intersect_key($payload, array_flip($updateCols));
                    $updatePayload['updated_at'] = now();

                    DB::connection($masterConn)->table('suppliers')
                        ->where('id', $masterSup->id)
                        ->update($updatePayload);

                    $idMapping[$sup->id] = (int) $masterSup->id;
                } else {
                    $payload['created_at'] = $payload['created_at'] ?? now();
                    $payload['updated_at'] = now();

                    // Intentar respetar el ID original si no existe colisión
                    if (DB::connection($masterConn)->table('suppliers')->where('id', $sup->id)->exists()) {
                        unset($payload['id']);
                    }

                    $newId = DB::connection($masterConn)->table('suppliers')->insertGetId($payload);
                    $idMapping[$sup->id] = (int) $newId;
                }

                $stats['suppliers_synced']++;
            }

            // 2. Sincronizar Estructuras Técnicas (supplier_connections)
            $sourceConnections = DB::table('supplier_connections')
                ->where(function ($q) {
                    $q->whereNotNull('structure')
                        ->orWhereNotNull('secondary_structure')
                        ->orWhereNotNull('tertiary_structure')
                        ->orWhereNotNull('invoice_structure')
                        ->orWhereIn('type', ['file', 'ftp', 'sftp', 'api', 'http', 'dronena_bot', 'drosymca_bot']);
                })
                ->get();

            $structuralFields = [
                'type',
                'host',
                'port',
                'pasv',
                'has_header',
                'structure',
                'secondary_structure',
                'tertiary_structure',
                'invoice_structure',
                'credit_days_cod_col',
                'credit_days_col',
            ];

            foreach ($sourceConnections as $srcConn) {
                $masterSupId = $idMapping[$srcConn->supplier_id] ?? null;
                if (!$masterSupId) {
                    continue;
                }

                $type = $srcConn->type ?? 'file';

                $existingMasterConn = DB::connection($masterConn)->table('supplier_connections')
                    ->where('supplier_id', $masterSupId)
                    ->where('type', $type)
                    ->first();

                // Host sanitizado: si es Excel/file, no guardar correos personales en el host
                $cleanHost = $srcConn->host;
                if ($type === 'file' && (empty($cleanHost) || str_contains((string) $cleanHost, '@'))) {
                    $supName = DB::connection($masterConn)->table('suppliers')->where('id', $masterSupId)->value('name');
                    $cleanHost = ($supName ?? 'Droguería') . ' (Excel)';
                }

                $structuralPayload = [];
                foreach ($structuralFields as $f) {
                    if (isset($srcConn->$f) && isset($masterConnCols[$f])) {
                        $structuralPayload[$f] = $srcConn->$f;
                    }
                }
                $structuralPayload['host'] = $cleanHost;
                $structuralPayload['updated_at'] = now();

                if ($existingMasterConn) {
                    if ($overwriteMasterStructures || empty($existingMasterConn->structure)) {
                        DB::connection($masterConn)->table('supplier_connections')
                            ->where('id', $existingMasterConn->id)
                            ->update($structuralPayload);
                        $stats['connections_synced']++;
                    }
                } else {
                    $newConnRow = array_merge($structuralPayload, [
                        'supplier_id' => $masterSupId,
                        'username' => null,
                        'password' => null,
                        'path' => null,
                        'invoice_path' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::connection($masterConn)->table('supplier_connections')->insert($newConnRow);
                    $stats['connections_synced']++;
                }
            }
        };

        // Si se indicó un tenant específico, buscarlo y ejecutar en su contexto
        if (!empty($sourceTenantId)) {
            $tenant = Tenant::find($sourceTenantId);
            if (!$tenant) {
                // Intentar buscar por coincidencia en nombre o ID
                $tenant = Tenant::where('id', 'LIKE', "%{$sourceTenantId}%")->first();
            }

            if ($tenant) {
                $tenant->run($extractor);
                return $stats;
            }
        }

        // Si no se encontró tenant o no se especificó, extraer desde la base de datos activa
        $extractor();

        return $stats;
    }

    /**
     * Sincroniza y consolida proveedores y estructuras técnicas desde la base activa y TODOS los tenants hacia el Master.
     *
     * @param bool $overwriteMasterStructures
     * @return array<string, int>
     */
    public function syncMasterFromAllTenants(bool $overwriteMasterStructures = true): array
    {
        $this->ensureMasterDatabaseAndTables();

        $totalStats = [
            'tenants_processed' => 0,
            'suppliers_synced' => 0,
            'connections_synced' => 0,
        ];

        // 1. Extraer desde la base de datos central / activa
        $baseStats = $this->syncMasterFromTenant(null, $overwriteMasterStructures);
        $totalStats['tenants_processed']++;
        $totalStats['suppliers_synced'] += $baseStats['suppliers_synced'];
        $totalStats['connections_synced'] += $baseStats['connections_synced'];

        // 2. Extraer desde cada uno de los tenants registrados
        $centralConn = config('tenancy.database.central_connection', 'mysql');
        if (Schema::connection($centralConn)->hasTable('tenants')) {
            $tenants = Tenant::all();
            foreach ($tenants as $tenant) {
                try {
                    $tenantStats = $this->syncMasterFromTenant((string) $tenant->id, $overwriteMasterStructures);
                    $totalStats['tenants_processed']++;
                    $totalStats['suppliers_synced'] += $tenantStats['suppliers_synced'];
                    $totalStats['connections_synced'] += $tenantStats['connections_synced'];
                } catch (\Throwable $e) {
                    Log::warning("Error extrayendo proveedores del tenant {$tenant->id} hacia el Master: " . $e->getMessage());
                }
            }
        }

        return $totalStats;
    }

    /**
     * Propaga el catálogo del Master ya consolidado hacia todos los tenants.
     *
     * @return array<string, int>
     */
    public function propagateToAllTenants(): array
    {
        return $this->supplierSyncService->syncAllTenants();
    }
}
