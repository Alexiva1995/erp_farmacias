<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SupplierSyncService
{
    /**
     * Obtiene los proveedores de la base de datos maestra (tovaerp_master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterSuppliers(): array
    {
        $masterConnection = 'master_catalog';

        if (!Schema::connection($masterConnection)->hasTable('suppliers')) {
            // Fallback a conexión central si aún no se inicializó tovaerp_master
            $masterConnection = config('tenancy.database.central_connection', 'mysql');
        }

        if (!Schema::connection($masterConnection)->hasTable('suppliers')) {
            return [];
        }

        $columns = Schema::connection($masterConnection)->getColumnListing('suppliers');

        return DB::connection($masterConnection)
            ->table('suppliers')
            ->select($columns)
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();
    }

    /**
     * Obtiene las conexiones estructurales de proveedores del master (sin credenciales privadas).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterConnections(): array
    {
        $masterConnection = 'master_catalog';

        if (!Schema::connection($masterConnection)->hasTable('supplier_connections')) {
            $masterConnection = config('tenancy.database.central_connection', 'mysql');
        }

        if (!Schema::connection($masterConnection)->hasTable('supplier_connections')) {
            return [];
        }

        $columns = Schema::connection($masterConnection)->getColumnListing('supplier_connections');

        return DB::connection($masterConnection)
            ->table('supplier_connections')
            ->select($columns)
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();
    }

    /**
     * Sincroniza los proveedores y sus estructuras técnicas hacia un tenant específico.
     * Respeta días de crédito y credenciales locales del tenant.
     *
     * @param Tenant $tenant
     * @param array<int, array<string, mixed>>|null $masterSuppliers
     * @param array<int, array<string, mixed>>|null $masterConnections
     * @return int Cantidad de registros sincronizados
     */
    public function syncTenant(
        Tenant $tenant,
        ?array $masterSuppliers = null,
        ?array $masterConnections = null
    ): int {
        $suppliers = $masterSuppliers ?? $this->getMasterSuppliers();
        $connections = $masterConnections ?? $this->getMasterConnections();

        if (empty($suppliers)) {
            return 0;
        }

        $tenant->run(function () use ($suppliers, $connections) {
            if (!Schema::hasTable('suppliers')) {
                return;
            }

            $tenantColumns = Schema::getColumnListing('suppliers');
            $tenantColsSet = array_flip($tenantColumns);

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            try {
                // 1. Sincronizar Proveedores
                // Para no pisar configuraciones comerciales del tenant (credit_days, etc.),
                // solo actualizamos datos de identidad fiscal si ya existe.
                $fiscalUpdateColumns = ['name', 'social_reason', 'rif', 'updated_at'];
                $availableFiscalUpdates = array_values(array_intersect($fiscalUpdateColumns, $tenantColumns));

                $filteredSuppliers = array_map(function ($sup) use ($tenantColsSet) {
                    return array_intersect_key($sup, $tenantColsSet);
                }, $suppliers);

                foreach (array_chunk($filteredSuppliers, 100) as $chunk) {
                    DB::table('suppliers')->upsert(
                        $chunk,
                        ['id'],
                        $availableFiscalUpdates
                    );
                }

                // 2. Sincronizar Estructura de SupplierConnections (Plantillas técnicas de droguerías)
                if (!empty($connections) && Schema::hasTable('supplier_connections')) {
                    $connColumns = Schema::getColumnListing('supplier_connections');
                    $structuralColumns = [
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
                        'updated_at',
                    ];
                    $allowedUpdateCols = array_values(array_intersect($structuralColumns, $connColumns));

                    foreach ($connections as $masterConn) {
                        $supplierId = (int) ($masterConn['supplier_id'] ?? 0);
                        if ($supplierId <= 0) {
                            continue;
                        }

                        $type = $masterConn['type'] ?? 'file';

                        $existing = DB::table('supplier_connections')
                            ->where('supplier_id', $supplierId)
                            ->where('type', $type)
                            ->first();

                        if ($existing) {
                            // Actualizar únicamente datos de mapeo y estructura técnica. NUNCA tocar username/password/path
                            $updatePayload = [];
                            foreach ($allowedUpdateCols as $col) {
                                if (array_key_exists($col, $masterConn)) {
                                    $updatePayload[$col] = $masterConn[$col];
                                }
                            }
                            if (!empty($updatePayload)) {
                                DB::table('supplier_connections')
                                    ->where('id', $existing->id)
                                    ->update($updatePayload);
                            }
                        } else {
                            // Crear la conexión nueva en el tenant con estructura limpia (credenciales vacías para configurar)
                            $newPayload = array_intersect_key($masterConn, array_flip($connColumns));
                            $newPayload['username'] = null;
                            $newPayload['password'] = null;
                            $newPayload['path'] = null;
                            $newPayload['invoice_path'] = null;
                            $newPayload['created_at'] = now();
                            $newPayload['updated_at'] = now();
                            unset($newPayload['id']); // Dejar que el tenant asigne su ID local o mantenga auto_increment

                            DB::table('supplier_connections')->insert($newPayload);
                        }
                    }
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        });

        return count($suppliers);
    }

    /**
     * Sincroniza los proveedores y estructuras del master hacia todos los tenants o uno en específico.
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

        $masterSuppliers = $this->getMasterSuppliers();
        $masterConnections = $this->getMasterConnections();

        if (empty($masterSuppliers)) {
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
                $count = $this->syncTenant($tenant, $masterSuppliers, $masterConnections);
                $results[(string) $tenant->id] = $count;
            } catch (\Throwable $e) {
                Log::error("Error sincronizando proveedores al tenant {$tenant->id}: " . $e->getMessage(), [
                    'tenant_id' => $tenant->id,
                    'exception' => $e,
                ]);
                $results[(string) $tenant->id] = -1;
            }
        }

        return $results;
    }

    /**
     * Propaga la estructura técnica de un proveedor (ej. plantilla de columnas Excel) a todos los tenants.
     * Actualiza únicamente campos de estructura técnica sin tocar credenciales ni datos privados locales.
     *
     * @param int $supplierId ID del proveedor en el Master
     * @param string $type Tipo de conexión (ej. 'file', 'ftp', 'api')
     * @param array<string, mixed> $payload Campos estructurales a propagar
     * @return array<string, bool> Resumen de tenants actualizados
     */
    public function propagateStructureToTenants(int $supplierId, string $type, array $payload): array
    {
        $centralConnection = config('tenancy.database.central_connection', 'mysql');
        if (!Schema::connection($centralConnection)->hasTable('tenants')) {
            return [];
        }

        // Obtener el proveedor master para conocer su RIF como identificador canónico
        $masterConnection = 'master_catalog';
        if (!Schema::connection($masterConnection)->hasTable('suppliers')) {
            $masterConnection = $centralConnection;
        }

        $masterSupplier = DB::connection($masterConnection)
            ->table('suppliers')
            ->where('id', $supplierId)
            ->first();

        $supplierRif = $masterSupplier?->rif;

        // Filtrar columnas estrictamente estructurales permitidas para propagación
        $allowedColumns = [
            'has_header',
            'pasv',
            'structure',
            'secondary_structure',
            'tertiary_structure',
            'invoice_structure',
            'credit_days_cod_col',
            'credit_days_col',
        ];

        $updateData = [];
        foreach ($allowedColumns as $col) {
            if (array_key_exists($col, $payload)) {
                $val = $payload[$col];
                // Si es array o null, formatearlo adecuadamente para la consulta SQL
                $updateData[$col] = is_array($val) ? json_encode($val) : $val;
            }
        }

        if (empty($updateData)) {
            return [];
        }

        $updateData['updated_at'] = now();

        $tenants = Tenant::all();
        $results = [];

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($supplierId, $supplierRif, $type, $updateData) {
                    if (!Schema::hasTable('suppliers') || !Schema::hasTable('supplier_connections')) {
                        return;
                    }

                    // Resolver los IDs correspondientes a este proveedor en el tenant
                    $localSupplierIds = [$supplierId];
                    if (!empty($supplierRif)) {
                        $matchedIds = DB::table('suppliers')
                            ->where('rif', $supplierRif)
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->all();
                        $localSupplierIds = array_values(array_unique(array_merge($localSupplierIds, $matchedIds)));
                    }

                    // Actualizar conexiones existentes que correspondan al proveedor y tipo
                    $affected = DB::table('supplier_connections')
                        ->whereIn('supplier_id', $localSupplierIds)
                        ->where('type', $type)
                        ->update($updateData);

                    // Si no existía la conexión pero el proveedor está instalado en el tenant, crear plantilla base
                    if ($affected === 0) {
                        $installedSuppliers = DB::table('suppliers')
                            ->whereIn('id', $localSupplierIds)
                            ->get();

                        foreach ($installedSuppliers as $sup) {
                            $insertPayload = array_merge($updateData, [
                                'supplier_id' => $sup->id,
                                'type' => $type,
                                'host' => $type === 'file' ? ($sup->name . ' (Excel)') : null,
                                'username' => null,
                                'password' => null,
                                'path' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            DB::table('supplier_connections')->insert($insertPayload);
                        }
                    }
                });

                $results[(string) $tenant->id] = true;
            } catch (\Throwable $e) {
                Log::error("Error propagando estructura a tenant {$tenant->id}: " . $e->getMessage(), [
                    'supplier_id' => $supplierId,
                    'type' => $type,
                    'exception' => $e,
                ]);
                $results[(string) $tenant->id] = false;
            }
        }

        return $results;
    }
}
