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
     * Obtiene los proveedores de la base de datos central (master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterSuppliers(): array
    {
        $centralConnection = config('tenancy.database.central_connection', 'mysql');

        if (!Schema::connection($centralConnection)->hasTable('suppliers')) {
            return [];
        }

        // Leer todas las columnas disponibles en la tabla de proveedores del master
        $columns = Schema::connection($centralConnection)->getColumnListing('suppliers');

        return DB::connection($centralConnection)
            ->table('suppliers')
            ->select($columns)
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();
    }

    /**
     * Sincroniza los proveedores hacia un tenant específico.
     *
     * @param Tenant $tenant
     * @param array<int, array<string, mixed>>|null $masterSuppliers
     * @return int Cantidad de registros sincronizados
     */
    public function syncTenant(Tenant $tenant, ?array $masterSuppliers = null): int
    {
        $suppliers = $masterSuppliers ?? $this->getMasterSuppliers();

        if (empty($suppliers)) {
            return 0;
        }

        $tenant->run(function () use ($suppliers) {
            if (!Schema::hasTable('suppliers')) {
                return;
            }

            $tenantColumns = Schema::getColumnListing('suppliers');
            $tenantColsSet = array_flip($tenantColumns);

            // Filtrar las columnas para no insertar campos inexistentes en el tenant
            $filteredSuppliers = array_map(function ($sup) use ($tenantColsSet) {
                return array_intersect_key($sup, $tenantColsSet);
            }, $suppliers);

            // Deshabilitar FK temporalmente para evitar problemas de índices o restricciones
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            try {
                // Determinar columnas actualizables excluyendo la PK 'id'
                $updateColumns = array_values(array_filter(
                    $tenantColumns,
                    fn ($col) => $col !== 'id'
                ));

                // Upsert por lotes de 100 registros
                foreach (array_chunk($filteredSuppliers, 100) as $chunk) {
                    DB::table('suppliers')->upsert(
                        $chunk,
                        ['id'],
                        $updateColumns
                    );
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        });

        return count($suppliers);
    }

    /**
     * Sincroniza los proveedores del master hacia todos los tenants o uno en específico.
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
                $count = $this->syncTenant($tenant, $masterSuppliers);
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
}
