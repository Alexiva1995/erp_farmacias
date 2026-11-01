<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MasterCatalogBranchService
{
    /**
     * Adjunta las sucursales donde cada producto está activo.
     *
     * @param iterable<\App\Models\Product> $products
     */
    public function attachActiveBranches(iterable $products): void
    {
        $productIds = [];
        foreach ($products as $p) {
            if (!empty($p->id)) {
                $productIds[] = (int) $p->id;
                $p->active_branches = [];
            }
        }

        if (empty($productIds)) {
            return;
        }

        $centralConn = config('tenancy.database.central_connection', 'mysql');

        try {
            if (!Schema::connection($centralConn)->hasTable('tenants')) {
                return;
            }

            $tenants = DB::connection($centralConn)->table('tenants')->get();
            if ($tenants->isEmpty()) {
                return;
            }

            $prefix = config('tenancy.database.prefix', 'tovaerp_tenant_');
            $suffix = config('tenancy.database.suffix', '');

            $productBranches = [];

            foreach ($tenants as $tenant) {
                $dbName = $prefix . $tenant->id . $suffix;
                $data = json_decode((string) ($tenant->data ?? '{}'), true);
                $branchName = $data['company_name'] ?? $data['name'] ?? ucfirst($tenant->id);

                try {
                    $activeIds = DB::connection($centralConn)->table("{$dbName}.products")
                        ->whereIn('id', $productIds)
                        ->where('is_active', 1)
                        ->where('is_deleted', 0)
                        ->pluck('id')
                        ->all();

                    foreach ($activeIds as $actId) {
                        $productBranches[$actId][] = [
                            'id' => $tenant->id,
                            'name' => $branchName,
                        ];
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            foreach ($products as $p) {
                $p->active_branches = $productBranches[$p->id] ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning('Error calculando sucursales activas en MasterCatalogBranchService: ' . $e->getMessage());
        }
    }
}
