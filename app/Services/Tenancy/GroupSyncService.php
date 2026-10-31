<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GroupSyncService
{
    /**
     * Obtiene los grupos de productos de la base de datos maestra (tovaerp_master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterGroups(): array
    {
        $masterConnection = 'master_catalog';
        if (!Schema::connection($masterConnection)->hasTable('groups_products')) {
            $masterConnection = config('tenancy.database.central_connection', 'mysql');
        }

        if (!Schema::connection($masterConnection)->hasTable('groups_products')) {
            return [];
        }

        return DB::connection($masterConnection)
            ->table('groups_products')
            ->select(['id', 'name', 'created_at', 'updated_at'])
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();
    }

    /**
     * Sincroniza los grupos de productos hacia un tenant específico.
     *
     * @param Tenant $tenant
     * @param array<int, array<string, mixed>>|null $masterGroups
     * @return int Cantidad de registros sincronizados
     */
    public function syncTenant(Tenant $tenant, ?array $masterGroups = null): int
    {
        $groups = $masterGroups ?? $this->getMasterGroups();

        if (empty($groups)) {
            return 0;
        }

        $tenant->run(function () use ($groups) {
            if (!Schema::hasTable('groups_products')) {
                return;
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            try {
                foreach (array_chunk($groups, 500) as $chunk) {
                    DB::table('groups_products')->upsert(
                        $chunk,
                        ['id'],
                        ['name', 'updated_at']
                    );
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        });

        return count($groups);
    }

    /**
     * Sincroniza los grupos del master hacia todos los tenants o uno en específico.
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

        $masterGroups = $this->getMasterGroups();
        if (empty($masterGroups)) {
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
                $count = $this->syncTenant($tenant, $masterGroups);
                $results[(string) $tenant->id] = $count;
            } catch (\Throwable $e) {
                Log::error("Error sincronizando grupos de productos al tenant {$tenant->id}: " . $e->getMessage(), [
                    'tenant_id' => $tenant->id,
                    'exception' => $e,
                ]);
                $results[(string) $tenant->id] = -1;
            }
        }

        return $results;
    }
}
