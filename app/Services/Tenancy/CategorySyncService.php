<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CategorySyncService
{
    /**
     * Obtiene las categorías de la base de datos maestra (tovaerp_master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterCategories(): array
    {
        $masterConnection = 'master_catalog';
        if (!Schema::connection($masterConnection)->hasTable('categories')) {
            $masterConnection = config('tenancy.database.central_connection', 'mysql');
        }

        if (!Schema::connection($masterConnection)->hasTable('categories')) {
            return [];
        }

        return DB::connection($masterConnection)
            ->table('categories')
            ->select(['id', 'name', 'created_at', 'updated_at'])
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();
    }

    /**
     * Sincroniza las categorías hacia un tenant específico.
     *
     * @param Tenant $tenant
     * @param array<int, array<string, mixed>>|null $masterCategories
     * @return int Cantidad de registros sincronizados
     */
    public function syncTenant(Tenant $tenant, ?array $masterCategories = null): int
    {
        $categories = $masterCategories ?? $this->getMasterCategories();

        if (empty($categories)) {
            return 0;
        }

        $tenant->run(function () use ($categories) {
            if (!Schema::hasTable('categories')) {
                return;
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            try {
                foreach (array_chunk($categories, 500) as $chunk) {
                    DB::table('categories')->upsert(
                        $chunk,
                        ['id'],
                        ['name', 'updated_at']
                    );
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        });

        return count($categories);
    }

    /**
     * Sincroniza las categorías del master hacia todos los tenants o uno en específico.
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

        $masterCategories = $this->getMasterCategories();
        if (empty($masterCategories)) {
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
                $count = $this->syncTenant($tenant, $masterCategories);
                $results[(string) $tenant->id] = $count;
            } catch (\Throwable $e) {
                Log::error("Error sincronizando categorías al tenant {$tenant->id}: " . $e->getMessage(), [
                    'tenant_id' => $tenant->id,
                    'exception' => $e,
                ]);
                $results[(string) $tenant->id] = -1;
            }
        }

        return $results;
    }
}
