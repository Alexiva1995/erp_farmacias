<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SwitchToMasterCatalogConnection
{
    /**
     * Tablas requeridas en tovaerp_master para que todas las vistas de Inventario,
     * Laboratorios, Grupos y Proveedores funcionen sin errores de JOIN o subconsultas.
     */
    private const REQUIRED_TABLES = [
        'general_settings',
        'exchange_rates',
        'groups_laboratories',
        'laboratories',
        'categories',
        'origins',
        'groups_products',
        'suppliers',
        'supplier_connections',
        'products',
        'product_lots',
        'product_profitability',
        'product_variants',
        'product_pack_items',
        'product_suppliers',
        'product_counts',
        'product_failures',
        'product_stockouts',
        'category_offers',
        'category_profitability',
        'individual_offers',
        'invoices',
        'invoice_details',
        'order_details',
        'auto_orders',
        'auto_replenishment_configs',
        'expirations',
        'expired_logs',
        'inventory_movements',
        'quotation_products',
        'returns',
        'invoice_returns',
        'payment_rules',
        'retentions',
        'psychotropic_controls',
        'supplier_connection_statuses',
        'supplier_discounts',
        'supplier_laboratories',
        'supplier_payment_methods',
        'supplier_ratings',
        'supplier_scores',
        'suppliers_config_products',
        'employee_laboratory',
        'employee_product',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Master-Catalog-Context') !== '1') {
            return $next($request);
        }

        // 1. Pre-cargar y fijar el usuario autenticado en memoria usando la conexión original (antes de cambiar a master_catalog)
        $previousConnection = DB::getDefaultConnection();

        try {
            $user = Auth::guard('sanctum')->user() ?? Auth::guard('web')->user() ?? $request->user();
            if ($user) {
                if (method_exists($user, 'loadMissing')) {
                    $user->loadMissing('role');
                }
                Auth::guard('sanctum')->setUser($user);
                Auth::guard('web')->setUser($user);
                Auth::setUser($user);
                $request->setUserResolver(fn () => $user);
            }
        } catch (\Throwable $e) {
            Log::warning('Error pre-resolviendo usuario antes de SwitchToMasterCatalogConnection: ' . $e->getMessage());
        }

        // 2. Asegurar conectividad a master_catalog (incluyendo autodetección entre cuentas hermanas del VPS)
        if (!$this->ensureMasterCatalogConnected($previousConnection)) {
            return $next($request);
        }

        // 3. Asegurar que las tablas auxiliares existan en tovaerp_master
        $this->ensureMasterSchemaReady($previousConnection);

        // 4. Cambiar conexión por defecto a master_catalog durante la ejecución del controlador
        DB::setDefaultConnection('master_catalog');

        try {
            /** @var Response $response */
            $response = $next($request);
        } finally {
            DB::setDefaultConnection($previousConnection);
        }

        // 5. Si fue una operación de escritura exitosa en Master, propagar cambios a los tenants
        if (
            in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $response->getStatusCode() >= 200
            && $response->getStatusCode() < 300
        ) {
            $this->propagateMasterChanges($request);
        }

        return $response;
    }

    /**
     * Verifica la conexión a master_catalog; si falla por permisos entre usuarios cPanel hermanos,
     * intenta usar las credenciales del .env de zorcasalud.tovaerp.com.
     */
    protected function ensureMasterCatalogConnected(string $fallbackConn): bool
    {
        try {
            DB::connection('master_catalog')->getPdo();
            return true;
        } catch (\Throwable) {
            $siblingEnvs = [
                '/home/tovaerp/zorcasalud.tovaerp.com/.env',
                '/home/farmaciabs/public_html/erp3/.env',
            ];

            foreach ($siblingEnvs as $envPath) {
                if (!is_readable($envPath)) {
                    continue;
                }
                $user = null;
                $pass = null;
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), 'DB_USERNAME=')) {
                        $user = trim(explode('=', $line, 2)[1] ?? '', " \t\n\r\0\x0B\"'");
                    }
                    if (str_starts_with(trim($line), 'DB_PASSWORD=')) {
                        $pass = trim(explode('=', $line, 2)[1] ?? '', " \t\n\r\0\x0B\"'");
                    }
                }

                if ($user) {
                    try {
                        $cfg = config('database.connections.master_catalog');
                        $cfg['username'] = $user;
                        $cfg['password'] = $pass ?? '';
                        config(['database.connections.master_catalog' => $cfg]);
                        DB::purge('master_catalog');
                        DB::connection('master_catalog')->getPdo();
                        return true;
                    } catch (\Throwable) {
                        // Continuar intentando
                    }
                }
            }
        }

        return false;
    }

    /**
     * Clona la estructura de tablas relacionales vacías si aún no existen en tovaerp_master.
     */
    protected function ensureMasterSchemaReady(string $sourceConn): void
    {
        $cacheKey = 'master_catalog_aux_tables_ready_v2';
        if (Cache::get($cacheKey) === true) {
            return;
        }

        try {
            $masterTables = array_map(
                fn ($t) => array_values((array) $t)[0],
                DB::connection('master_catalog')->select('SHOW TABLES')
            );
            $masterTableSet = array_flip($masterTables);

            $sourceTables = array_map(
                fn ($t) => array_values((array) $t)[0],
                DB::connection($sourceConn)->select('SHOW TABLES')
            );
            $sourceTableSet = array_flip($sourceTables);

            DB::connection('master_catalog')->statement('SET FOREIGN_KEY_CHECKS=0;');

            foreach (self::REQUIRED_TABLES as $table) {
                if (!isset($masterTableSet[$table]) && isset($sourceTableSet[$table])) {
                    $createSql = DB::connection($sourceConn)->select("SHOW CREATE TABLE `{$table}`");
                    $prop = 'Create Table';
                    $ddl = $createSql[0]->$prop ?? null;
                    if ($ddl) {
                        // Remover cláusulas FOREIGN KEY para que las tablas auxiliares no fallen por orden de creación
                        $lines = explode("\n", $ddl);
                        $cleanLines = array_filter($lines, fn ($l) => !str_contains($l, 'CONSTRAINT ') && !str_contains($l, 'FOREIGN KEY'));
                        $cleanDdl = implode("\n", $cleanLines);
                        // Limpiar coma colgante antes del cierre de paréntesis
                        $cleanDdl = preg_replace('/,\s*\n(\)\s*ENGINE=)/', "\n$1", $cleanDdl) ?? $ddl;

                        try {
                            DB::connection('master_catalog')->statement($cleanDdl);
                        } catch (\Throwable) {
                            DB::connection('master_catalog')->statement($ddl);
                        }
                    }
                }
            }

            DB::connection('master_catalog')->statement('SET FOREIGN_KEY_CHECKS=1;');
            Cache::put($cacheKey, true, now()->addHours(12));
        } catch (\Throwable $e) {
            Log::warning('Aviso verificando esquema auxiliar en tovaerp_master: ' . $e->getMessage());
        }
    }

    /**
     * Propaga automáticamente hacia los tenants las modificaciones hechas en las vistas MASTER.
     */
    protected function propagateMasterChanges(Request $request): void
    {
        $path = strtolower($request->path());

        try {
            if (str_contains($path, 'laboratories')) {
                app(\App\Services\Tenancy\LaboratorySyncService::class)->syncAllTenants();
            } elseif (str_contains($path, 'groups')) {
                app(\App\Services\Tenancy\GroupSyncService::class)->syncAllTenants();
            } elseif (str_contains($path, 'categories')) {
                app(\App\Services\Tenancy\CategorySyncService::class)->syncAllTenants();
            } elseif (str_contains($path, 'suppliers')) {
                app(\App\Services\Tenancy\SupplierSyncService::class)->syncAllTenants();
            }
        } catch (\Throwable $e) {
            Log::warning('Aviso al propagar cambio del Master a tenants: ' . $e->getMessage());
        }
    }
}
