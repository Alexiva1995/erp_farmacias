<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LaboratorySyncService
{
    /**
     * Obtiene los grupos de laboratorios de la base de datos central (master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterGroupsLaboratories(): array
    {
        $centralConnection = config('tenancy.database.central_connection', 'mysql');

        if (!Schema::connection($centralConnection)->hasTable('groups_laboratories')) {
            return [];
        }

        return DB::connection($centralConnection)
            ->table('groups_laboratories')
            ->select(['id', 'name', 'created_at', 'updated_at'])
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();
    }

    /**
     * Obtiene los laboratorios de la base de datos central (master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterLaboratories(): array
    {
        $centralConnection = config('tenancy.database.central_connection', 'mysql');

        $query = DB::connection($centralConnection)->table('laboratories');
        $columns = ['id', 'name', 'group_id', 'created_at', 'updated_at'];

        if (Schema::connection($centralConnection)->hasColumn('laboratories', 'parent_id')) {
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

            // 2. Sincronizar laboratorios deshabilitando FK temporalmente para manejar parent_id / group_id de forma segura
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
                    // Procesar en chunks para evitar límites de tamaño en query de MySQL
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
}
