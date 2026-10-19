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
     * Obtiene las categorías de la base de datos central (master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterCategories(): array
    {
        $centralConnection = config('tenancy.database.central_connection', 'mysql');

        return DB::connection($centralConnection)
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
            DB::table('categories')->upsert(
                $categories,
                ['id'],
                ['name', 'updated_at']
            );
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
                $results[$tenant->id] = $count;
            } catch (\Throwable $e) {
                Log::error("Error sincronizando categorías en tenant {$tenant->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                $results[$tenant->id] = -1;
            }
        }

        return $results;
    }
}
