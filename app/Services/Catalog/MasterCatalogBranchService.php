<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;
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
        $barcodeMap = [];

        foreach ($products as $p) {
            if (!empty($p->id)) {
                $id = (int) $p->id;
                $productIds[] = $id;
                $p->active_branches = [];

                $barcode = trim((string) ($p->barcode ?? ''));
                if ($barcode !== '') {
                    $barcodeMap[$barcode] = $id;
                }
            }
        }

        if (empty($productIds)) {
            return;
        }

        $branches = $this->getAvailableBranches();
        if (empty($branches)) {
            return;
        }

        $productBranches = [];
        $barcodes = array_keys($barcodeMap);

        foreach ($branches as $branch) {
            $connKey = $branch['conn_key'];

            try {
                if (!Schema::connection($connKey)->hasTable('products')) {
                    continue;
                }

                $hasIsActive = Schema::connection($connKey)->hasColumn('products', 'is_active');
                $hasIsDeleted = Schema::connection($connKey)->hasColumn('products', 'is_deleted');
                $hasBarcode = !empty($barcodes) && Schema::connection($connKey)->hasColumn('products', 'barcode');

                $query = DB::connection($connKey)->table('products');

                if ($hasIsDeleted) {
                    $query->where('is_deleted', 0);
                }

                if ($hasIsActive) {
                    $query->where(function ($q) {
                        $q->where('is_active', 1)->orWhereNull('is_active');
                    });
                }

                $query->where(function ($q) use ($productIds, $barcodes, $hasBarcode) {
                    $q->whereIn('id', $productIds);
                    if ($hasBarcode && !empty($barcodes)) {
                        $q->orWhereIn('barcode', $barcodes);
                    }
                });

                $selectCols = ['id'];
                if ($hasBarcode) {
                    $selectCols[] = 'barcode';
                }

                $rows = $query->select($selectCols)->get();
                $matchedMasterIds = [];

                foreach ($rows as $row) {
                    $rId = (int) $row->id;
                    if (in_array(
Id, $productIds, true)) {
                        $matchedMasterIds[$rId] = true;
                    }

                    if ($hasBarcode && !empty($row->barcode)) {
                        $b = trim((string) $row->barcode);
                        if (isset($barcodeMap[$b])) {
                            $matchedMasterIds[$barcodeMap[$b]] = true;
                        }
                    }
                }

                foreach (array_keys($matchedMasterIds) as $mId) {
                    $productBranches[$mId][] = [
                        'id' => $branch['id'],
                        'name' => $branch['name'],
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        foreach ($products as $p) {
            $p->active_branches = $productBranches[(int) $p->id] ?? [];
        }
    }

    /**
     * Detecta y configura las conexiones de todas las sucursales conocidas
     * (base local, bases hermanas en VPS y tenants multi-empresa).
     *
     * @return array<int, array{id: string, name: string, conn_key: string}>
     */
    public function getAvailableBranches(): array
    {
        return Cache::remember('master_catalog_active_branches_list_v4', 300, function () {
            $branches = [];
            $configuredDatabases = [];

            $centralConn = config('tenancy.database.central_connection', 'mysql');
            $localDb = config("database.connections.{$centralConn}.database");

            // 1. Sucursal local / actual (ej. Farmacia BS o Zorca según el dominio)
            if (!empty($localDb) && $localDb !== 'tovaerp_master') {
                $localName = $this->resolveBranchName($centralConn, $localDb);
                $branches[] = [
                    'id' => $localDb,
                    'name' => $localName,
                    'conn_key' => $centralConn,
                ];
                $configuredDatabases[$localDb] = true;
            }

            // 2. Tenants registrados en la base central actual
            try {
                if (Schema::connection($centralConn)->hasTable('tenants')) {
                    $tenants = DB::connection($centralConn)->table('tenants')->get();
                    $prefix = config('tenancy.database.prefix', 'tovaerp_tenant_');
                    $suffix = config('tenancy.database.suffix', '');

                    foreach ($tenants as $t) {
                        $tDb = $prefix . $t->id . $suffix;
                        if (!isset($configuredDatabases[$tDb])) {
                            $connKey = 'branch_t_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $t->id);
                            if ($this->configureConnection($connKey, $tDb, null, $centralConn)) {
                                $data = json_decode((string) ($t->data ?? '{}'), true);
                                $branches[] = [
                                    'id' => (string) $t->id,
                                    'name' => $data['company_name'] ?? $data['name'] ?? ucfirst((string) $t->id),
                                    'conn_key' => $connKey,
                                ];
                                $configuredDatabases[$tDb] = true;
                            }
                        }
                    }
                }
            } catch (\Throwable) {
            }

            // 3. Cuentas hermanas en VPS cPanel (farmaciabs y tovaerp/zorcasalud)
            $siblingConfigs = [
                'farmaciabs_erp2' => [
                    'env' => '/home/farmaciabs/public_html/erp3/.env',
                    'name' => 'Farmacia BS',
                ],
                'tovaerp_zorca' => [
                    'env' => '/home/tovaerp/zorcasalud.tovaerp.com/.env',
                    'name' => 'Zorca Salud',
                ],
            ];

            foreach ($siblingConfigs as $dbName => $info) {
                if (isset($configuredDatabases[$dbName])) {
                    continue;
                }

                $connKey = 'branch_sib_' . $dbName;
                if ($this->configureConnection($connKey, $dbName, $info['env'], $centralConn)) {
                    $branches[] = [
                        'id' => $dbName,
                        'name' => $info['name'],
                        'conn_key' => $connKey,
                    ];
                    $configuredDatabases[$dbName] = true;

                    // Si esta cuenta hermana tiene la tabla tenants, leer sus tenants también
                    try {
                        if (Schema::connection($connKey)->hasTable('tenants')) {
                            $sTenants = DB::connection($connKey)->table('tenants')->get();
                            $prefix = 'tovaerp_tenant_';

                            foreach ($sTenants as $st) {
                                $stDb = $prefix . $st->id;
                                if (!isset($configuredDatabases[$stDb])) {
                                    $stConnKey = 'branch_st_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $st->id);
                                    if ($this->configureConnection($stConnKey, $stDb, $info['env'], $centralConn)) {
                                        $stData = json_decode((string) ($st->data ?? '{}'), true);
                                        $branches[] = [
                                            'id' => (string) $st->id,
                                            'name' => $stData['company_name'] ?? $stData['name'] ?? ucfirst((string) $st->id),
                                            'conn_key' => $stConnKey,
                                        ];
                                        $configuredDatabases[$stDb] = true;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable) {
                    }
                }
            }

            // 4. Entornos locales de prueba (si no hay sucursales detectadas en VPS)
            if (count($branches) <= 1) {
                $testDbs = [
                    'farmaciabs' => 'Farmacia BS',
                    'fama' => 'Fama',
                    'kolo' => 'Kolo',
                    'victoria' => 'Victoria',
                    'minimarket' => 'Minimarket',
                ];

                foreach ($testDbs as $tDb => $tName) {
                    if (isset($configuredDatabases[$tDb])) {
                        continue;
                    }

                    $testConn = 'branch_local_' . $tDb;
                    if ($this->configureConnection($testConn, $tDb, null, $centralConn)) {
                        $branches[] = [
                            'id' => $tDb,
                            'name' => $tName,
                            'conn_key' => $testConn,
                        ];
                        $configuredDatabases[$tDb] = true;
                    }
                }
            }

            return $branches;
        });
    }

    /**
     * Resuelve el nombre amigable de una base de datos o instalación.
     */
    protected function resolveBranchName(string $conn, string $dbName): string
    {
        try {
            if (Schema::connection($conn)->hasTable('general_settings')) {
                $appName = DB::connection($conn)->table('general_settings')->value('app_name');
                if (!empty($appName)) {
                    return (string) $appName;
                }
            }
        } catch (\Throwable) {
        }

        return match ($dbName) {
            'farmaciabs', 'farmaciabs_erp2' => 'Farmacia BS',
            'tovaerp_zorca'                 => 'Zorca Salud',
            'minimarket'                     => 'Minimarket Central',
            default                          => ucfirst(str_replace(['tovaerp_tenant_', 'tovaerp_', '_'], ['', '', ' '], $dbName)),
        };
    }

    /**
     * Configura y valida una conexión dinámica a base de datos.
     */
    protected function configureConnection(
        string $connKey,
        string $databaseName,
        ?string $envPath,
        string $baseConn
    ): bool {
        $baseConfig = config("database.connections.{$baseConn}") ?? config('database.connections.mysql');
        if (!is_array($baseConfig)) {
            return false;
        }

        $cfg = $baseConfig;
        $cfg['database'] = $databaseName;

        // Intentar primero con credenciales del .env hermano si existe
        if (!empty($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), 'DB_USERNAME=')) {
                    $cfg['username'] = trim(explode('=', $line, 2)[1] ?? '', " \t\n\r\0\x0B\"'");
                }
                if (str_starts_with(trim($line), 'DB_PASSWORD=')) {
                    $cfg['password'] = trim(explode('=', $line, 2)[1] ?? '', " \t\n\r\0\x0B\"'");
                }
                if (str_starts_with(trim($line), 'DB_HOST=')) {
                    $cfg['host'] = trim(explode('=', $line, 2)[1] ?? '', " \t\n\r\0\x0B\"'");
                }
            }
        }

        config(["database.connections.{$connKey}" => $cfg]);
        DB::purge($connKey);

        try {
            DB::connection($connKey)->getPdo();
            return true;
        } catch (\Throwable) {
            // Reintentar con credenciales base por si comparte usuario
            if (!empty($envPath)) {
                $cfg['username'] = $baseConfig['username'] ?? '';
                $cfg['password'] = $baseConfig['password'] ?? '';
                $cfg['host'] = $baseConfig['host'] ?? '127.0.0.1';
                config(["database.connections.{$connKey}" => $cfg]);
                DB::purge($connKey);
                try {
                    DB::connection($connKey)->getPdo();
                    return true;
                } catch (\Throwable) {
                }
            }
        }

        return false;
    }
}
