<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OriginSyncService
{
    /**
     * Obtiene los orígenes de la base de datos maestra (tovaerp_master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterOrigins(): array
    {
        $masterConnection = 'master_catalog';
        if (!Schema::connection($masterConnection)->hasTable('origins')) {
            $masterConnection = config('tenancy.database.central_connection', 'mysql');
        }

        if (!Schema::connection($masterConnection)->hasTable('origins')) {
            return [];
        }

        return DB::connection($masterConnection)
            ->table('origins')
            ->select(['id', 'name', 'created_at', 'updated_at'])
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();
    }

    /**
     * Sincroniza los orígenes hacia un tenant específico.
     *
     * @param Tenant $tenant
     * @param array<int, array<string, mixed>>|null $masterOrigins
     * @return int Cantidad de registros sincronizados
     */
    public function syncTenant(Tenant $tenant, ?array $masterOrigins = null): int
    {
        $origins = $masterOrigins ?? $this->getMasterOrigins();

        if (empty($origins)) {
            return 0;
        }

        $tenant->run(function () use ($origins) {
            if (!Schema::hasTable('origins')) {
                return;
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            try {
                foreach (array_chunk($origins, 500) as $chunk) {
                    DB::table('origins')->upsert(
                        $chunk,
                        ['id'],
                        ['name', 'updated_at']
                    );
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        });

        return count($origins);
    }

    /**
     * Sincroniza los orígenes del master hacia todos los tenants o uno en específico.
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

        $masterOrigins = $this->getMasterOrigins();
        if (empty($masterOrigins)) {
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
                $count = $this->syncTenant($tenant, $masterOrigins);
                $results[(string) $tenant->id] = $count;
            } catch (\Throwable $e) {
                Log::error("Error sincronizando orígenes al tenant {$tenant->id}: " . $e->getMessage(), [
                    'tenant_id' => $tenant->id,
                    'exception' => $e,
                ]);
                $results[(string) $tenant->id] = -1;
            }
        }

        return $results;
    }
}
