<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Models\Domain;

class TenantManagementService
{
    /**
     * Listar todos los tenants registrados y detectar bases de datos huérfanas en MySQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAllTenantsAndOrphans(): array
    {
        $result = [];
        $registeredDbNames = [];

        // 1. Obtener tenants registrados
        if (Schema::hasTable('tenants')) {
            $tenants = Tenant::with('domains')
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($tenants as $tenant) {
                /** @var Tenant $tenant */
                $dbName = $tenant->database()->getName();
                $registeredDbNames[] = strtolower($dbName);

                $resource = (new TenantResource($tenant))->resolve();
                $resource['database'] = $dbName;
                $resource['is_orphan'] = false;

                $result[] = $resource;
            }
        }

        // 2. Obtener bases de datos físicas en MySQL
        try {
            $prefix = (string) config('tenancy.database.prefix', 'tovaerp_tenant_');
            $rawDatabases = collect(DB::select('SHOW DATABASES'))->pluck('Database')->values();

            // Filtrar bases de datos sospechosas de tenants del ERP
            $tenantDatabases = $rawDatabases->filter(function (string $dbName) use ($prefix, $registeredDbNames): bool {
                $lower = strtolower($dbName);
                return str_starts_with($lower, strtolower($prefix))
                    || str_starts_with($lower, 'tovaerp_')
                    || str_contains($lower, 'tenant')
                    || in_array($lower, $registeredDbNames, true);
            });

            // 3. Agregar como elementos virtuales las bases de datos huérfanas
            foreach ($tenantDatabases as $dbName) {
                if (!in_array(strtolower($dbName), $registeredDbNames, true)) {
                    // Deducir un identificador limpio
                    $cleanId = str_starts_with($dbName, $prefix)
                        ? substr($dbName, strlen($prefix))
                        : (str_starts_with($dbName, 'tovaerp_') ? substr($dbName, strlen('tovaerp_')) : $dbName);

                    $result[] = [
                        'id' => $cleanId,
                        'company_name' => "BD Huérfana ({$dbName})",
                        'database' => $dbName,
                        'vertical' => 'orphan',
                        'is_orphan' => true,
                        'domains' => [],
                        'created_at' => null,
                        'updated_at' => null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron escanear bases de datos de MySQL para tenants: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Eliminar un tenant, sus dominios y su base de datos física de MySQL.
     *
     * @param string $id
     * @param string|null $databaseName
     * @return array<string, mixed>
     */
    public function deleteTenantAndDatabase(string $id, ?string $databaseName = null): array
    {
        $tenant = Tenant::find($id);

        // 1. Determinar el nombre exacto de la base de datos
        $dbPrefix = (string) config('tenancy.database.prefix', 'tovaerp_tenant_');
        $dbName = $databaseName;

        if (empty($dbName)) {
            if ($tenant) {
                $dbName = $tenant->database()->getName();
            } else {
                $dbName = str_starts_with($id, 'tovaerp_') ? $id : $dbPrefix . $id;
            }
        }

        // 2. Eliminar dominios asociados en base central
        Domain::where('tenant_id', $id)->delete();

        // 3. Eliminar base de datos física en MySQL
        if (!empty($dbName)) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Throwable $e) {
                Log::error("Error al ejecutar DROP DATABASE para `{$dbName}`: " . $e->getMessage());
            }
        }

        // 4. Eliminar registro del tenant en base central
        if ($tenant) {
            $tenant->delete();
        } else {
            Tenant::where('id', $id)->delete();
        }

        return [
            'success' => true,
            'message' => "La empresa '{$id}' y su base de datos '{$dbName}' fueron eliminadas permanentemente.",
        ];
    }
}
