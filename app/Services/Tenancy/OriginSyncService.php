<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OriginSyncService
{
    /**
     * Obtiene los orígenes registrados en la base de datos central (master).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterOrigins(): array
    {
        $centralConnection = config('tenancy.database.central_connection', 'mysql');

        return DB::connection($centralConnection)
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
            DB::table('origins')->upsert(
                $origins,
                ['id'],
                ['name', 'updated_at']
            );
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
                $results[$tenant->id] = $count;
            } catch (\Throwable $e) {
                Log::error("Error sincronizando orígenes en tenant {$tenant->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                $results[$tenant->id] = -1;
            }
        }

        return $results;
    }
}
