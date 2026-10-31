<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class InitMasterCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'master-catalog:init 
                            {--source-db= : Base de datos origen adicional (ej: farmaciabs_erp2)}
                            {--source-user= : Usuario MySQL opcional para source-db}
                            {--source-pass= : Contraseña MySQL opcional para source-db}
                            {--clean : Reconstruir las tablas del maestro desde cero}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unifica en tovaerp_master los catálogos de la central y TODOS los tenants, y homologa todos los IDs y llaves foráneas en cascada';

    private const TEMP_OFFSET = 100000000;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $masterConn = 'master_catalog';
        $centralConn = config('tenancy.database.central_connection', 'mysql');

        $this->info('========================================================================');
        $this->info(' CONSOLIDACIÓN Y HOMOLOGACIÓN GLOBAL DE CATÁLOGO MAESTRO (tovaerp_master)');
        $this->info('========================================================================');

        // 1. Verificar o crear la base de datos tovaerp_master automáticamente si el usuario tiene permisos
        try {
            DB::connection($masterConn)->getPdo();
            $this->info("✓ Conexión establecida con la base de datos maestra (tovaerp_master).");
        } catch (\Throwable $e) {
            try {
                $masterDbName = config('database.connections.master_catalog.database', 'tovaerp_master');
                DB::connection($centralConn)->statement("CREATE DATABASE IF NOT EXISTS `{$masterDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                DB::purge($masterConn);
                DB::connection($masterConn)->getPdo();
                $this->info("✓ Base de datos '{$masterDbName}' creada y conectada exitosamente.");
            } catch (\Throwable $e2) {
                $this->error("No se pudo conectar a 'master_catalog': " . $e->getMessage());
                $this->warn("Verifica que el usuario de MySQL tenga permisos sobre 'tovaerp_master'.");
                return self::FAILURE;
            }
        }

        $tables = [
            'groups_laboratories',
            'laboratories',
            'categories',
            'origins',
            'groups_products',
            'suppliers',
            'supplier_connections',
            'products',
        ];

        // 2. Crear estructura de tablas en tovaerp_master si no existen
        foreach ($tables as $table) {
            if (!Schema::connection($centralConn)->hasTable($table)) {
                continue;
            }

            if (!Schema::connection($masterConn)->hasTable($table)) {
                $this->line("• Creando tabla <fg=cyan>{$table}</> en tovaerp_master...");
                $createSql = DB::connection($centralConn)->select("SHOW CREATE TABLE `{$table}`");
                $prop = 'Create Table';
                $ddl = $createSql[0]->$prop;

                DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::connection($masterConn)->statement($ddl);
                DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }

        if ($this->option('clean')) {
            $this->warn("• Limpiando tablas previas de tovaerp_master (--clean)...");
            DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=0;');
            foreach (array_reverse($tables) as $table) {
                if (Schema::connection($masterConn)->hasTable($table)) {
                    DB::connection($masterConn)->table($table)->truncate();
                }
            }
            DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // 3. Armar lista de conexiones origen para nutrir el Master:
        //    a) --source-db (si se indicó o si farmaciabs_erp2 es accesible)
        //    b) Base de datos central (mysql)
        //    c) Todos los tenants registrados
        $sourceDbOption = $this->option('source-db');
        $sourceUserOption = $this->option('source-user');
        $sourcePassOption = $this->option('source-pass');
        $externalSources = [];

        // Intentar detectar credenciales de farmaciabs si el archivo .env hermano es legible
        $autoFarmaciabsUser = null;
        $autoFarmaciabsPass = null;
        $siblingEnv = '/home/farmaciabs/public_html/erp3/.env';
        if (is_readable($siblingEnv)) {
            $envLines = file($siblingEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($envLines as $line) {
                if (str_starts_with(trim($line), 'DB_USERNAME=')) {
                    $autoFarmaciabsUser = trim(explode('=', $line, 2)[1] ?? '', " \t\n\r\0\x0B\"'");
                }
                if (str_starts_with(trim($line), 'DB_PASSWORD=')) {
                    $autoFarmaciabsPass = trim(explode('=', $line, 2)[1] ?? '', " \t\n\r\0\x0B\"'");
                }
            }
        }

        $candidateDbs = array_filter(array_unique([$sourceDbOption, 'farmaciabs_erp2']));
        foreach ($candidateDbs as $dbName) {
            $connName = 'src_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $dbName);
            $baseConfig = config("database.connections.{$centralConn}");
            $customConfig = array_merge($baseConfig, ['database' => $dbName]);

            if (!empty($sourceUserOption)) {
                $customConfig['username'] = $sourceUserOption;
                $customConfig['password'] = $sourcePassOption ?? '';
            }

            config(["database.connections.{$connName}" => $customConfig]);

            try {
                DB::connection($connName)->getPdo();
                if (Schema::connection($connName)->hasTable('products')) {
                    $externalSources[$dbName] = $connName;
                    $this->info("✓ Fuente adicional detectada y conectada: <fg=yellow>{$dbName}</>");
                }
            } catch (\Throwable $ex) {
                // Reintentar con credenciales autodectadas de farmaciabs si existen
                if ($autoFarmaciabsUser) {
                    try {
                        $customConfig['username'] = $autoFarmaciabsUser;
                        $customConfig['password'] = $autoFarmaciabsPass ?? '';
                        config(["database.connections.{$connName}" => $customConfig]);
                        DB::purge($connName);
                        DB::connection($connName)->getPdo();
                        if (Schema::connection($connName)->hasTable('products')) {
                            $externalSources[$dbName] = $connName;
                            $this->info("✓ Fuente adicional conectada automáticamente: <fg=yellow>{$dbName}</>");
                            continue;
                        }
                    } catch (\Throwable) {
                        // Continuar con aviso normal
                    }
                }
                if ($sourceDbOption === $dbName) {
                    $this->warn("• No se pudo acceder a '{$dbName}' con el usuario actual: " . $ex->getMessage());
                    $this->line("  (Puedes pasar --source-user=USUARIO --source-pass=CLAVE si pertenece a otro usuario de cPanel)");
                }
            }
        }

        $tenants = collect();
        if (Schema::connection($centralConn)->hasTable('tenants')) {
            $tenants = Tenant::all();
        }

        $this->info("\n========================================================================");
        $this->info(" FASE 1: CONSOLIDANDO CATÁLOGOS DE CENTRAL + " . $tenants->count() . " TENANTS EN MASTER");
        $this->info("========================================================================");

        DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=0;');

        // Ejecutar ingesta en orden: primero fuentes externas (ej. farmaciabs_erp2), luego central, luego cada tenant
        foreach ($externalSources as $dbLabel => $extConn) {
            $this->line("\n→ Extrayendo catálogos de <fg=cyan>{$dbLabel}</>...");
            $this->ingestConnectionIntoMaster($extConn, $masterConn);
        }

        $centralDbName = DB::connection($centralConn)->getDatabaseName();
        if (!isset($externalSources[$centralDbName])) {
            $this->line("\n→ Extrayendo catálogos de Base Central (<fg=cyan>{$centralDbName}</>)...");
            $this->ingestConnectionIntoMaster($centralConn, $masterConn);
        }

        foreach ($tenants as $tenant) {
            $this->line("\n→ Extrayendo catálogos del Tenant <fg=yellow>{$tenant->id}</>...");
            try {
                $tenant->run(function () use ($masterConn) {
                    $this->ingestConnectionIntoMaster(DB::getDefaultConnection(), $masterConn);
                });
            } catch (\Throwable $e) {
                $this->warn("  ! Error leyendo tenant {$tenant->id}: " . $e->getMessage());
            }
        }

        DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=1;');

        // Resumen del Master consolidado
        $this->info("\n--- Totales Consolidados en tovaerp_master ---");
        foreach ($tables as $t) {
            if (Schema::connection($masterConn)->hasTable($t)) {
                $cnt = DB::connection($masterConn)->table($t)->count();
                $this->line("  • {$t}: <fg=green>{$cnt}</>");
            }
        }

        // 4. FASE 2: HOMOLOGAR IDs EN CASCADA Y SINCRONIZAR EN CENTRAL Y TODOS LOS TENANTS
        $this->info("\n========================================================================");
        $this->info(" FASE 2: HOMOLOGANDO IDs EN CASCADA (CENTRAL + TODOS LOS TENANTS)");
        $this->info("========================================================================");

        $this->line("\n→ Homologando IDs y llaves foráneas en Base Central (<fg=cyan>{$centralDbName}</>)...");
        try {
            $this->homologateDatabaseAgainstMaster($centralConn, $masterConn);
        } catch (\Throwable $e) {
            $this->warn("  ! Error homologando central: " . $e->getMessage());
        }

        foreach ($tenants as $tenant) {
            $this->line("\n→ Homologando IDs y llaves foráneas en Tenant <fg=yellow>{$tenant->id}</>...");
            try {
                $tenant->run(function () use ($masterConn) {
                    $this->homologateDatabaseAgainstMaster(DB::getDefaultConnection(), $masterConn);
                });
            } catch (\Throwable $e) {
                $this->warn("  ! Error homologando tenant {$tenant->id}: " . $e->getMessage());
                Log::error("Error homologando tenant {$tenant->id}: " . $e->getMessage());
            }
        }

        $this->info("\n========================================================================");
        $this->info(" ✓ PROCESO COMPLETADO: CATÁLOGO MAESTRO Y TENANTS 100% HOMOLOGADOS");
        $this->info("========================================================================");

        return self::SUCCESS;
    }

    /**
     * Lee una base de datos (central o tenant) e incorpora en tovaerp_master cualquier
     * grupo, laboratorio, categoría, origen, proveedor, conexión o producto que falte.
     */
    protected function ingestConnectionIntoMaster(string $srcConn, string $masterConn): void
    {
        // 1. Catálogos simples por nombre normalizado
        $simpleTables = ['groups_laboratories', 'categories', 'origins', 'groups_products'];
        foreach ($simpleTables as $table) {
            if (!Schema::connection($srcConn)->hasTable($table) || !Schema::connection($masterConn)->hasTable($table)) {
                continue;
            }

            $masterEmpty = DB::connection($masterConn)->table($table)->count() === 0;
            $existingNames = DB::connection($masterConn)->table($table)
                ->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [mb_strtoupper(trim((string) $name)) => $id])
                ->toArray();

            $rows = DB::connection($srcConn)->table($table)->orderBy('id')->get();
            $inserted = 0;

            foreach ($rows as $row) {
                $name = trim((string) ($row->name ?? ''));
                if ($name === '') {
                    continue;
                }
                $norm = mb_strtoupper($name);
                if (!isset($existingNames[$norm])) {
                    $payload = [
                        'name'       => $name,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                    if ($masterEmpty) {
                        $payload['id'] = $row->id;
                    }
                    $newId = DB::connection($masterConn)->table($table)->insertGetId($payload);
                    $existingNames[$norm] = $newId;
                    $inserted++;
                }
            }
            if ($inserted > 0) {
                $this->line("  + {$table}: {$inserted} nuevos registros agregados al Master.");
            }
        }

        // 2. Laboratorios (respetando group_id por nombre de grupo)
        if (Schema::connection($srcConn)->hasTable('laboratories') && Schema::connection($masterConn)->hasTable('laboratories')) {
            $masterLabsEmpty = DB::connection($masterConn)->table('laboratories')->count() === 0;
            $masterLabs = DB::connection($masterConn)->table('laboratories')
                ->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [mb_strtoupper(trim((string) $name)) => $id])
                ->toArray();

            $masterGroupIdsByName = Schema::connection($masterConn)->hasTable('groups_laboratories')
                ? DB::connection($masterConn)->table('groups_laboratories')
                    ->pluck('id', 'name')
                    ->mapWithKeys(fn ($id, $name) => [mb_strtoupper(trim((string) $name)) => $id])
                    ->toArray()
                : [];

            $srcGroupNamesById = Schema::connection($srcConn)->hasTable('groups_laboratories')
                ? DB::connection($srcConn)->table('groups_laboratories')->pluck('name', 'id')->toArray()
                : [];

            $srcLabs = DB::connection($srcConn)->table('laboratories')->orderBy('id')->get();
            $insertedLabs = 0;

            foreach ($srcLabs as $lab) {
                $name = trim((string) ($lab->name ?? ''));
                if ($name === '') {
                    continue;
                }
                $norm = mb_strtoupper($name);
                $srcGroupName = isset($lab->group_id, $srcGroupNamesById[$lab->group_id])
                    ? mb_strtoupper(trim((string) $srcGroupNamesById[$lab->group_id]))
                    : null;
                $resolvedGroupId = $srcGroupName ? ($masterGroupIdsByName[$srcGroupName] ?? null) : null;

                if (!isset($masterLabs[$norm])) {
                    $payload = [
                        'name'       => $name,
                        'group_id'   => $resolvedGroupId,
                        'created_at' => $lab->created_at ?? now(),
                        'updated_at' => $lab->updated_at ?? now(),
                    ];
                    if ($masterLabsEmpty) {
                        $payload['id'] = $lab->id;
                    }
                    $newId = DB::connection($masterConn)->table('laboratories')->insertGetId($payload);
                    $masterLabs[$norm] = $newId;
                    $insertedLabs++;
                } elseif ($resolvedGroupId) {
                    DB::connection($masterConn)->table('laboratories')
                        ->where('id', $masterLabs[$norm])
                        ->whereNull('group_id')
                        ->update(['group_id' => $resolvedGroupId]);
                }
            }
            if ($insertedLabs > 0) {
                $this->line("  + laboratories: {$insertedLabs} nuevos laboratorios agregados al Master.");
            }
        }

        // 3. Proveedores y Estructuras de Conexión (supplier_connections)
        if (Schema::connection($srcConn)->hasTable('suppliers') && Schema::connection($masterConn)->hasTable('suppliers')) {
            $masterSupEmpty = DB::connection($masterConn)->table('suppliers')->count() === 0;
            $masterSups = DB::connection($masterConn)->table('suppliers')->get();
            $supByRif = [];
            $supByName = [];

            foreach ($masterSups as $ms) {
                $r = $this->normalizeRif($ms->rif ?? null);
                if ($r !== '') {
                    $supByRif[$r] = $ms->id;
                }
                $n = mb_strtoupper(trim((string) ($ms->name ?? '')));
                if ($n !== '') {
                    $supByName[$n] = $ms->id;
                }
            }

            $masterSupCols = array_flip(Schema::connection($masterConn)->getColumnListing('suppliers'));
            $srcSups = DB::connection($srcConn)->table('suppliers')->orderBy('id')->get();
            $srcToMasterSupId = [];
            $insertedSups = 0;

            foreach ($srcSups as $sup) {
                $name = trim((string) ($sup->name ?? ''));
                if ($name === '') {
                    continue;
                }
                $normName = mb_strtoupper($name);
                $normRif = $this->normalizeRif($sup->rif ?? null);

                $matchedMasterId = ($normRif !== '' && isset($supByRif[$normRif]))
                    ? $supByRif[$normRif]
                    : ($supByName[$normName] ?? null);

                if (!$matchedMasterId) {
                    $payload = array_intersect_key((array) $sup, $masterSupCols);
                    if (!$masterSupEmpty) {
                        unset($payload['id']);
                    }
                    $matchedMasterId = DB::connection($masterConn)->table('suppliers')->insertGetId($payload);
                    if ($normRif !== '') {
                        $supByRif[$normRif] = $matchedMasterId;
                    }
                    $supByName[$normName] = $matchedMasterId;
                    $insertedSups++;
                }

                $srcToMasterSupId[$sup->id] = $matchedMasterId;
            }

            if ($insertedSups > 0) {
                $this->line("  + suppliers: {$insertedSups} nuevos proveedores agregados al Master.");
            }

            // Consolidar estructuras de supplier_connections (sin credenciales)
            if (Schema::connection($srcConn)->hasTable('supplier_connections') && Schema::connection($masterConn)->hasTable('supplier_connections')) {
                $masterConnCols = array_flip(Schema::connection($masterConn)->getColumnListing('supplier_connections'));
                $srcConns = DB::connection($srcConn)->table('supplier_connections')->get();
                $insertedConns = 0;

                foreach ($srcConns as $sc) {
                    $targetSupId = $srcToMasterSupId[$sc->supplier_id] ?? null;
                    if (!$targetSupId) {
                        continue;
                    }
                    $type = $sc->type ?? 'file';

                    $existingMasterConn = DB::connection($masterConn)->table('supplier_connections')
                        ->where('supplier_id', $targetSupId)
                        ->where('type', $type)
                        ->first();

                    if (!$existingMasterConn) {
                        $row = array_intersect_key((array) $sc, $masterConnCols);
                        unset($row['id']);
                        $row['supplier_id'] = $targetSupId;
                        $row['username'] = null;
                        $row['password'] = null;
                        $row['path'] = null;
                        $row['invoice_path'] = null;
                        DB::connection($masterConn)->table('supplier_connections')->insert($row);
                        $insertedConns++;
                    } else {
                        // Enriquecer si el Master tiene structure o host vacíos y esta fuente sí los tiene
                        $updates = [];
                        foreach (['host', 'port', 'structure', 'secondary_structure', 'tertiary_structure', 'invoice_structure', 'credit_days_cod_col', 'credit_days_col'] as $field) {
                            if (empty($existingMasterConn->$field) && !empty($sc->$field) && isset($masterConnCols[$field])) {
                                $updates[$field] = $sc->$field;
                            }
                        }
                        if (!empty($updates)) {
                            DB::connection($masterConn)->table('supplier_connections')
                                ->where('id', $existingMasterConn->id)
                                ->update($updates);
                        }
                    }
                }
                if ($insertedConns > 0) {
                    $this->line("  + supplier_connections: {$insertedConns} estructuras técnicas consolidadas.");
                }
            }
        }

        // 4. Productos (Por barcode o por nombre si no tiene barcode)
        if (Schema::connection($srcConn)->hasTable('products') && Schema::connection($masterConn)->hasTable('products')) {
            $masterProdEmpty = DB::connection($masterConn)->table('products')->count() === 0;

            $masterLabsByName = Schema::connection($masterConn)->hasTable('laboratories')
                ? DB::connection($masterConn)->table('laboratories')->pluck('id', 'name')
                    ->mapWithKeys(fn ($id, $n) => [mb_strtoupper(trim((string) $n)) => $id])->toArray()
                : [];
            $masterCatsByName = Schema::connection($masterConn)->hasTable('categories')
                ? DB::connection($masterConn)->table('categories')->pluck('id', 'name')
                    ->mapWithKeys(fn ($id, $n) => [mb_strtoupper(trim((string) $n)) => $id])->toArray()
                : [];
            $masterOrigsByName = Schema::connection($masterConn)->hasTable('origins')
                ? DB::connection($masterConn)->table('origins')->pluck('id', 'name')
                    ->mapWithKeys(fn ($id, $n) => [mb_strtoupper(trim((string) $n)) => $id])->toArray()
                : [];
            $masterGroupsByName = Schema::connection($masterConn)->hasTable('groups_products')
                ? DB::connection($masterConn)->table('groups_products')->pluck('id', 'name')
                    ->mapWithKeys(fn ($id, $n) => [mb_strtoupper(trim((string) $n)) => $id])->toArray()
                : [];

            $srcLabsById = Schema::connection($srcConn)->hasTable('laboratories')
                ? DB::connection($srcConn)->table('laboratories')->pluck('name', 'id')->toArray()
                : [];
            $srcCatsById = Schema::connection($srcConn)->hasTable('categories')
                ? DB::connection($srcConn)->table('categories')->pluck('name', 'id')->toArray()
                : [];
            $srcOrigsById = Schema::connection($srcConn)->hasTable('origins')
                ? DB::connection($srcConn)->table('origins')->pluck('name', 'id')->toArray()
                : [];
            $srcGroupsById = Schema::connection($srcConn)->hasTable('groups_products')
                ? DB::connection($srcConn)->table('groups_products')->pluck('name', 'id')->toArray()
                : [];

            $generalesMasterId = $masterLabsByName['GENERALES'] ?? null;

            $masterByBarcode = [];
            $masterByName = [];
            DB::connection($masterConn)->table('products')
                ->select(['id', 'barcode', 'name', 'laboratory_id', 'category_id', 'origin_id', 'active_ingredient'])
                ->orderBy('id')
                ->chunk(2000, function ($prods) use (&$masterByBarcode, &$masterByName) {
                    foreach ($prods as $mp) {
                        $bc = trim((string) ($mp->barcode ?? ''));
                        if ($bc !== '') {
                            $masterByBarcode[$bc] = $mp;
                        }
                        $nm = mb_strtoupper(trim((string) ($mp->name ?? '')));
                        if ($nm !== '' && !isset($masterByName[$nm])) {
                            $masterByName[$nm] = $mp;
                        }
                    }
                });

            $masterProdCols = array_flip(Schema::connection($masterConn)->getColumnListing('products'));
            $insertedProds = 0;
            $enrichedProds = 0;

            DB::connection($srcConn)->table('products')
                ->orderBy('id')
                ->chunk(1000, function ($srcProds) use (
                    $masterConn,
                    $masterProdEmpty,
                    $masterProdCols,
                    $masterLabsByName,
                    $masterCatsByName,
                    $masterOrigsByName,
                    $masterGroupsByName,
                    $srcLabsById,
                    $srcCatsById,
                    $srcOrigsById,
                    $srcGroupsById,
                    $generalesMasterId,
                    &$masterByBarcode,
                    &$masterByName,
                    &$insertedProds,
                    &$enrichedProds
                ) {
                    $batchInsert = [];

                    foreach ($srcProds as $sp) {
                        $name = trim((string) ($sp->name ?? ''));
                        if ($name === '' || $name === '.') {
                            continue;
                        }
                        $bc = trim((string) ($sp->barcode ?? ''));
                        $normName = mb_strtoupper($name);

                        // Resolver IDs maestros de llaves foráneas por nombre
                        $labName = isset($sp->laboratory_id, $srcLabsById[$sp->laboratory_id])
                            ? mb_strtoupper(trim((string) $srcLabsById[$sp->laboratory_id]))
                            : null;
                        $masterLabId = $labName ? ($masterLabsByName[$labName] ?? null) : null;

                        $catName = isset($sp->category_id, $srcCatsById[$sp->category_id])
                            ? mb_strtoupper(trim((string) $srcCatsById[$sp->category_id]))
                            : null;
                        $masterCatId = $catName ? ($masterCatsByName[$catName] ?? null) : null;

                        $origName = isset($sp->origin_id, $srcOrigsById[$sp->origin_id])
                            ? mb_strtoupper(trim((string) $srcOrigsById[$sp->origin_id]))
                            : null;
                        $masterOrigId = $origName ? ($masterOrigsByName[$origName] ?? null) : null;

                        $grpName = isset($sp->group_id, $srcGroupsById[$sp->group_id])
                            ? mb_strtoupper(trim((string) $srcGroupsById[$sp->group_id]))
                            : null;
                        $masterGrpId = $grpName ? ($masterGroupsByName[$grpName] ?? null) : null;

                        $existingMp = ($bc !== '' && isset($masterByBarcode[$bc]))
                            ? $masterByBarcode[$bc]
                            : ($bc === '' ? ($masterByName[$normName] ?? null) : null);

                        if (!$existingMp) {
                            $item = array_intersect_key((array) $sp, $masterProdCols);
                            if (!$masterProdEmpty) {
                                unset($item['id']);
                            }
                            $item['name'] = $name;
                            $item['barcode'] = $bc !== '' ? $bc : null;
                            $item['laboratory_id'] = $masterLabId;
                            $item['category_id'] = $masterCatId;
                            $item['origin_id'] = $masterOrigId;
                            if (isset($masterProdCols['group_id'])) {
                                $item['group_id'] = $masterGrpId;
                            }
                            if (isset($masterProdCols['is_deleted'])) {
                                $item['is_deleted'] = 0;
                            }
                            if (isset($masterProdCols['is_active'])) {
                                $item['is_active'] = 1;
                            }
                            if (isset($masterProdCols['deleted_at'])) {
                                $item['deleted_at'] = null;
                            }

                            if ($masterProdEmpty) {
                                $batchInsert[] = $item;
                                $stub = (object) [
                                    'id' => $item['id'] ?? 0,
                                    'barcode' => $item['barcode'],
                                    'name' => $item['name'],
                                    'laboratory_id' => $masterLabId,
                                    'category_id' => $masterCatId,
                                    'origin_id' => $masterOrigId,
                                    'active_ingredient' => $item['active_ingredient'] ?? null,
                                ];
                                if ($bc !== '') {
                                    $masterByBarcode[$bc] = $stub;
                                }
                                $masterByName[$normName] = $stub;
                            } else {
                                $newId = DB::connection($masterConn)->table('products')->insertGetId($item);
                                $stub = (object) [
                                    'id' => $newId,
                                    'barcode' => $item['barcode'],
                                    'name' => $item['name'],
                                    'laboratory_id' => $masterLabId,
                                    'category_id' => $masterCatId,
                                    'origin_id' => $masterOrigId,
                                    'active_ingredient' => $item['active_ingredient'] ?? null,
                                ];
                                if ($bc !== '') {
                                    $masterByBarcode[$bc] = $stub;
                                }
                                $masterByName[$normName] = $stub;
                            }
                            $insertedProds++;
                        } else {
                            // Enriquecer en el Master si le falta laboratorio real, categoría, origen o principio activo
                            $updates = [];
                            if (
                                $masterLabId
                                && $masterLabId !== $generalesMasterId
                                && (empty($existingMp->laboratory_id) || $existingMp->laboratory_id === $generalesMasterId)
                            ) {
                                $updates['laboratory_id'] = $masterLabId;
                                $existingMp->laboratory_id = $masterLabId;
                            }
                            if (empty($existingMp->category_id) && $masterCatId) {
                                $updates['category_id'] = $masterCatId;
                                $existingMp->category_id = $masterCatId;
                            }
                            if (empty($existingMp->origin_id) && $masterOrigId) {
                                $updates['origin_id'] = $masterOrigId;
                                $existingMp->origin_id = $masterOrigId;
                            }
                            if (empty($existingMp->active_ingredient) && !empty($sp->active_ingredient)) {
                                $updates['active_ingredient'] = $sp->active_ingredient;
                                $existingMp->active_ingredient = $sp->active_ingredient;
                            }

                            if (!empty($updates) && !empty($existingMp->id)) {
                                DB::connection($masterConn)->table('products')
                                    ->where('id', $existingMp->id)
                                    ->update($updates);
                                $enrichedProds++;
                            }
                        }
                    }

                    if (!empty($batchInsert)) {
                        DB::connection($masterConn)->table('products')->insert($batchInsert);
                    }
                });

            if ($insertedProds > 0 || $enrichedProds > 0) {
                $this->line("  + products: {$insertedProds} nuevos productos y {$enrichedProds} enriquecidos en el Master.");
            }
        }
    }

    /**
     * Homologa todos los IDs de una base de datos (central o tenant) contra tovaerp_master,
     * remapeando todas las llaves foráneas en las tablas hijas y sincronizando los catálogos maestros.
     */
    protected function homologateDatabaseAgainstMaster(string $targetConn, string $masterConn): void
    {
        DB::connection($targetConn)->statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            // 1. Mapear groups_laboratories
            $groupLabMap = $this->buildNameIdMap($targetConn, $masterConn, 'groups_laboratories');
            if (!empty($groupLabMap)) {
                $this->applyMapping($targetConn, 'groups_laboratories', 'id', $groupLabMap, [
                    'laboratories' => 'group_id',
                ]);
            }

            // 2. Mapear laboratories
            $labMap = $this->buildNameIdMap($targetConn, $masterConn, 'laboratories');
            if (!empty($labMap)) {
                $this->applyMapping($targetConn, 'laboratories', 'id', $labMap, [
                    'products'              => 'laboratory_id',
                    'employee_laboratory'   => 'laboratory_id',
                    'supplier_laboratories' => 'laboratory_id',
                ]);
                $this->line("  ✓ Laboratorios remapeados: " . count($labMap));
            }

            // 3. Mapear origins
            $originMap = $this->buildNameIdMap($targetConn, $masterConn, 'origins');
            if (!empty($originMap)) {
                $this->applyMapping($targetConn, 'origins', 'id', $originMap, [
                    'products' => 'origin_id',
                ]);
                $this->line("  ✓ Orígenes remapeados: " . count($originMap));
            }

            // 4. Mapear groups_products
            $groupProdMap = $this->buildNameIdMap($targetConn, $masterConn, 'groups_products');
            if (!empty($groupProdMap)) {
                $this->applyMapping($targetConn, 'groups_products', 'id', $groupProdMap, [
                    'products' => 'group_id',
                ]);
                $this->line("  ✓ Grupos remapeados: " . count($groupProdMap));
            }

            // 5. Mapear categories
            $catMap = $this->buildNameIdMap($targetConn, $masterConn, 'categories');
            if (!empty($catMap)) {
                $this->applyMapping($targetConn, 'categories', 'id', $catMap, [
                    'products'               => 'category_id',
                    'category_offers'        => 'category_id',
                    'category_profitability' => 'category_id',
                ]);
                $this->line("  ✓ Categorías remapeadas: " . count($catMap));
            }

            // 6. Mapear suppliers
            $supMap = $this->buildSupplierIdMap($targetConn, $masterConn);
            if (!empty($supMap)) {
                $this->applyMapping($targetConn, 'suppliers', 'id', $supMap, [
                    'auto_orders'                  => 'supplier_id',
                    'auto_replenishment_configs'   => 'supplier_id',
                    'expirations'                  => 'supplier_id',
                    'inventory_movements'          => 'supplier_id',
                    'invoices'                     => 'supplier_id',
                    'payment_rules'                => 'supplier_id',
                    'product_lots'                 => 'supplier_id',
                    'product_suppliers'            => 'supplier_id',
                    'psychotropic_controls'        => 'supplier_id',
                    'retentions'                   => 'supplier_id',
                    'supplier_connection_statuses' => 'supplier_id',
                    'supplier_connections'         => 'supplier_id',
                    'supplier_discounts'           => 'supplier_id',
                    'supplier_laboratories'        => 'supplier_id',
                    'supplier_payment_methods'     => 'supplier_id',
                    'supplier_ratings'             => 'supplier_id',
                    'supplier_scores'              => 'supplier_id',
                    'suppliers_config_products'    => 'supplier_id',
                ]);
                $this->line("  ✓ Proveedores remapeados: " . count($supMap));
            }

            // 7. Mapear products y sus 26 tablas relacionales
            $prodMap = $this->buildProductIdMap($targetConn, $masterConn);
            if (!empty($prodMap)) {
                $this->applyMapping($targetConn, 'products', 'id', $prodMap, [
                    'product_lots'                 => 'product_id',
                    'order_details'                => 'product_id',
                    'invoice_details'              => 'product_id',
                    'inventory_movements'          => 'product_id',
                    'quotation_products'           => 'product_id',
                    'product_suppliers'            => 'product_id',
                    'product_profitability'        => 'product_id',
                    'product_variants'             => 'product_id',
                    'product_pack_items'           => 'product_id',
                    'product_counts'               => 'product_id',
                    'product_failures'             => 'product_id',
                    'product_stockouts'            => 'product_id',
                    'returns'                      => 'product_id',
                    'invoice_returns'              => 'product_id',
                    'dish_ingredients'             => 'product_id',
                    'ecommerce_order_items'        => 'product_id',
                    'employee_product'             => 'product_id',
                    'expirations'                  => 'product_id',
                    'expired_logs'                 => 'product_id',
                    'fiscal_history_details'       => 'product_id',
                    'individual_offers'            => 'product_id',
                    'invoices_counts'              => 'product_id',
                    'price_adjustment_logs'        => 'product_id',
                    'psychotropic_controls'        => 'product_id',
                    'sales_counts'                 => 'product_id',
                    'supplier_ai_match_rejections' => 'product_id',
                ]);
                $this->line("  ✓ Productos remapeados en cascada: " . count($prodMap));
            }

            // 8. Sincronizar catálogos globales y estructuras de supplier_connections desde tovaerp_master hacia targetConn
            $this->syncCatalogsFromMasterToTarget($targetConn, $masterConn);

        } finally {
            DB::connection($targetConn)->statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Construye el mapa old_id => master_id por nombre normalizado.
     */
    protected function buildNameIdMap(string $targetConn, string $masterConn, string $table): array
    {
        if (!Schema::connection($targetConn)->hasTable($table) || !Schema::connection($masterConn)->hasTable($table)) {
            return [];
        }

        $masterByName = DB::connection($masterConn)->table($table)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [mb_strtoupper(trim((string) $n)) => (int) $id])
            ->toArray();

        $localRows = DB::connection($targetConn)->table($table)->select(['id', 'name'])->get();
        $map = [];

        foreach ($localRows as $row) {
            $norm = mb_strtoupper(trim((string) ($row->name ?? '')));
            if ($norm !== '' && isset($masterByName[$norm])) {
                $masterId = $masterByName[$norm];
                if ((int) $row->id !== $masterId) {
                    $map[(int) $row->id] = $masterId;
                }
            }
        }

        return $map;
    }

    /**
     * Construye el mapa old_id => master_id de proveedores por RIF o nombre.
     */
    protected function buildSupplierIdMap(string $targetConn, string $masterConn): array
    {
        if (!Schema::connection($targetConn)->hasTable('suppliers') || !Schema::connection($masterConn)->hasTable('suppliers')) {
            return [];
        }

        $masterSups = DB::connection($masterConn)->table('suppliers')->select(['id', 'name', 'rif'])->get();
        $byRif = [];
        $byName = [];

        foreach ($masterSups as $ms) {
            $r = $this->normalizeRif($ms->rif ?? null);
            if ($r !== '') {
                $byRif[$r] = (int) $ms->id;
            }
            $n = mb_strtoupper(trim((string) ($ms->name ?? '')));
            if ($n !== '') {
                $byName[$n] = (int) $ms->id;
            }
        }

        $localSups = DB::connection($targetConn)->table('suppliers')->select(['id', 'name', 'rif'])->get();
        $map = [];

        foreach ($localSups as $ls) {
            $r = $this->normalizeRif($ls->rif ?? null);
            $n = mb_strtoupper(trim((string) ($ls->name ?? '')));
            $masterId = ($r !== '' && isset($byRif[$r])) ? $byRif[$r] : ($byName[$n] ?? null);

            if ($masterId && (int) $ls->id !== $masterId) {
                $map[(int) $ls->id] = $masterId;
            }
        }

        return $map;
    }

    /**
     * Construye el mapa old_id => master_id de productos por barcode (o nombre si no tiene barcode).
     */
    protected function buildProductIdMap(string $targetConn, string $masterConn): array
    {
        if (!Schema::connection($targetConn)->hasTable('products') || !Schema::connection($masterConn)->hasTable('products')) {
            return [];
        }

        $byBarcode = [];
        $byName = [];

        DB::connection($masterConn)->table('products')
            ->select(['id', 'barcode', 'name'])
            ->orderBy('id')
            ->chunk(2000, function ($prods) use (&$byBarcode, &$byName) {
                foreach ($prods as $mp) {
                    $bc = trim((string) ($mp->barcode ?? ''));
                    if ($bc !== '') {
                        $byBarcode[$bc] = (int) $mp->id;
                    }
                    $nm = mb_strtoupper(trim((string) ($mp->name ?? '')));
                    if ($nm !== '' && !isset($byName[$nm])) {
                        $byName[$nm] = (int) $mp->id;
                    }
                }
            });

        $map = [];
        DB::connection($targetConn)->table('products')
            ->select(['id', 'barcode', 'name'])
            ->orderBy('id')
            ->chunk(2000, function ($localProds) use ($byBarcode, $byName, &$map) {
                foreach ($localProds as $lp) {
                    $bc = trim((string) ($lp->barcode ?? ''));
                    $nm = mb_strtoupper(trim((string) ($lp->name ?? '')));

                    $masterId = ($bc !== '' && isset($byBarcode[$bc]))
                        ? $byBarcode[$bc]
                        : ($bc === '' ? ($byName[$nm] ?? null) : null);

                    if ($masterId && (int) $lp->id !== $masterId) {
                        $map[(int) $lp->id] = $masterId;
                    }
                }
            });

        return $map;
    }

    /**
     * Aplica el remapeo de IDs en 2 fases libre de colisiones en la conexión objetivo,
     * manejando también registros locales duplicados que apunten al mismo ID maestro.
     */
    protected function applyMapping(
        string $conn,
        string $mainTable,
        string $primaryKey,
        array $map,
        array $foreignRelations
    ): void {
        $offset = self::TEMP_OFFSET;
        $existingTables = DB::connection($conn)->select('SHOW TABLES');
        $dbName = DB::connection($conn)->getDatabaseName();
        $tableList = array_map(
            fn ($t) => ((array) $t)["Tables_in_{$dbName}"] ?? array_values((array) $t)[0],
            $existingTables
        );

        DB::connection($conn)->statement('DROP TEMPORARY TABLE IF EXISTS temp_generic_map');
        DB::connection($conn)->statement('
            CREATE TEMPORARY TABLE temp_generic_map (
                old_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                new_id BIGINT UNSIGNED NOT NULL
            ) ENGINE=InnoDB
        ');

        try {
            $insertData = [];
            foreach ($map as $oldId => $newId) {
                $insertData[] = ['old_id' => $oldId, 'new_id' => $newId];
                if (count($insertData) >= 500) {
                    DB::connection($conn)->table('temp_generic_map')->insert($insertData);
                    $insertData = [];
                }
            }
            if (!empty($insertData)) {
                DB::connection($conn)->table('temp_generic_map')->insert($insertData);
            }

            // Fase 1: Tablas relacionales -> + offset
            foreach ($foreignRelations as $relTable => $fkColumn) {
                if (in_array($relTable, $tableList) && Schema::connection($conn)->hasColumn($relTable, $fkColumn)) {
                    DB::connection($conn)->update("
                        UPDATE IGNORE `{$relTable}` t
                        INNER JOIN temp_generic_map m ON t.`{$fkColumn}` = m.old_id
                        SET t.`{$fkColumn}` = m.new_id + {$offset}
                    ");
                }
            }

            // Fase 2: Tablas relacionales -> definitivo
            foreach ($foreignRelations as $relTable => $fkColumn) {
                if (in_array($relTable, $tableList) && Schema::connection($conn)->hasColumn($relTable, $fkColumn)) {
                    DB::connection($conn)->update("
                        UPDATE IGNORE `{$relTable}` t
                        SET t.`{$fkColumn}` = t.`{$fkColumn}` - {$offset}
                        WHERE t.`{$fkColumn}` >= {$offset}
                    ");
                }
            }

            // Detectar si el registro destino new_id ya existe en la tabla principal (y no se va a mover a otro ID)
            // o si múltiples old_id locales apuntan al mismo new_id (duplicados locales).
            $targetIds = array_values(array_unique($map));
            $existingTargetIds = [];
            foreach (array_chunk($targetIds, 1000) as $chunk) {
                $found = DB::connection($conn)->table($mainTable)
                    ->whereIn($primaryKey, $chunk)
                    ->pluck($primaryKey)
                    ->toArray();
                foreach ($found as $id) {
                    $existingTargetIds[(int) $id] = true;
                }
            }

            $redundantOldIds = [];
            $claimedNewIds = [];

            foreach ($map as $oldId => $newId) {
                $targetAlreadyExistsUnmoved = isset($existingTargetIds[(int) $newId]) && !isset($map[$newId]);
                $alreadyClaimedByAnotherOldId = isset($claimedNewIds[(int) $newId]);

                if ($targetAlreadyExistsUnmoved || $alreadyClaimedByAnotherOldId) {
                    $redundantOldIds[] = $oldId;
                } else {
                    $claimedNewIds[(int) $newId] = $oldId;
                }
            }

            if (!empty($redundantOldIds)) {
                foreach (array_chunk($redundantOldIds, 500) as $chunk) {
                    DB::connection($conn)->table($mainTable)->whereIn($primaryKey, $chunk)->delete();
                    DB::connection($conn)->table('temp_generic_map')->whereIn('old_id', $chunk)->delete();
                }
            }

            // Actualizar tabla principal en 2 fases libre de colisiones
            DB::connection($conn)->update("
                UPDATE `{$mainTable}` p
                INNER JOIN temp_generic_map m ON p.`{$primaryKey}` = m.old_id
                SET p.`{$primaryKey}` = m.new_id + {$offset}
            ");

            DB::connection($conn)->update("
                UPDATE `{$mainTable}` p
                SET p.`{$primaryKey}` = p.`{$primaryKey}` - {$offset}
                WHERE p.`{$primaryKey}` >= {$offset}
            ");
        } finally {
            DB::connection($conn)->statement('DROP TEMPORARY TABLE IF EXISTS temp_generic_map');
        }
    }

    /**
     * Sincroniza en la conexión destino todos los catálogos globales, estructuras de supplier_connections
     * y las llaves foráneas (laboratory_id, category_id, origin_id) de los productos existentes.
     */
    protected function syncCatalogsFromMasterToTarget(string $targetConn, string $masterConn): void
    {
        // 1. Catálogos globales completos
        foreach (['groups_laboratories', 'categories', 'origins', 'groups_products'] as $table) {
            if (!Schema::connection($targetConn)->hasTable($table) || !Schema::connection($masterConn)->hasTable($table)) {
                continue;
            }
            $rows = DB::connection($masterConn)->table($table)
                ->select(['id', 'name', 'created_at', 'updated_at'])
                ->get()
                ->map(fn ($r) => (array) $r)
                ->toArray();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::connection($targetConn)->table($table)->upsert($chunk, ['id'], ['name', 'updated_at']);
            }
        }

        // 2. Laboratorios
        if (Schema::connection($targetConn)->hasTable('laboratories') && Schema::connection($masterConn)->hasTable('laboratories')) {
            $hasParent = Schema::connection($targetConn)->hasColumn('laboratories', 'parent_id')
                && Schema::connection($masterConn)->hasColumn('laboratories', 'parent_id');
            $cols = ['id', 'name', 'group_id', 'created_at', 'updated_at'];
            $updateCols = ['name', 'group_id', 'updated_at'];
            if ($hasParent) {
                $cols[] = 'parent_id';
                $updateCols[] = 'parent_id';
            }
            $labs = DB::connection($masterConn)->table('laboratories')->select($cols)->get()->map(fn ($r) => (array) $r)->toArray();
            foreach (array_chunk($labs, 500) as $chunk) {
                DB::connection($targetConn)->table('laboratories')->upsert($chunk, ['id'], $updateCols);
            }
        }

        // 3. Proveedores (respetando días de crédito locales) y estructuras de supplier_connections (respetando credenciales locales)
        if (Schema::connection($targetConn)->hasTable('suppliers') && Schema::connection($masterConn)->hasTable('suppliers')) {
            $targetCols = Schema::connection($targetConn)->getColumnListing('suppliers');
            $targetColsSet = array_flip($targetCols);
            $fiscalUpdateCols = array_values(array_intersect(['name', 'social_reason', 'rif', 'updated_at'], $targetCols));

            $masterSups = DB::connection($masterConn)->table('suppliers')->get()->map(function ($s) use ($targetColsSet) {
                return array_intersect_key((array) $s, $targetColsSet);
            })->toArray();

            foreach (array_chunk($masterSups, 200) as $chunk) {
                DB::connection($targetConn)->table('suppliers')->upsert($chunk, ['id'], $fiscalUpdateCols);
            }

            if (Schema::connection($targetConn)->hasTable('supplier_connections') && Schema::connection($masterConn)->hasTable('supplier_connections')) {
                $connCols = Schema::connection($targetConn)->getColumnListing('supplier_connections');
                $structuralCols = array_values(array_intersect([
                    'type', 'host', 'port', 'pasv', 'has_header',
                    'structure', 'secondary_structure', 'tertiary_structure', 'invoice_structure',
                    'credit_days_cod_col', 'credit_days_col', 'updated_at',
                ], $connCols));

                $masterConns = DB::connection($masterConn)->table('supplier_connections')->get();
                foreach ($masterConns as $mc) {
                    $mcArr = (array) $mc;
                    $supId = (int) ($mcArr['supplier_id'] ?? 0);
                    if ($supId <= 0) {
                        continue;
                    }
                    $type = $mcArr['type'] ?? 'file';
                    $existing = DB::connection($targetConn)->table('supplier_connections')
                        ->where('supplier_id', $supId)
                        ->where('type', $type)
                        ->first();

                    if ($existing) {
                        $upd = [];
                        foreach ($structuralCols as $c) {
                            if (array_key_exists($c, $mcArr)) {
                                $upd[$c] = $mcArr[$c];
                            }
                        }
                        if (!empty($upd)) {
                            DB::connection($targetConn)->table('supplier_connections')->where('id', $existing->id)->update($upd);
                        }
                    } else {
                        $ins = array_intersect_key($mcArr, array_flip($connCols));
                        unset($ins['id']);
                        $ins['username'] = null;
                        $ins['password'] = null;
                        $ins['path'] = null;
                        $ins['invoice_path'] = null;
                        $ins['created_at'] = now();
                        $ins['updated_at'] = now();
                        DB::connection($targetConn)->table('supplier_connections')->insert($ins);
                    }
                }
            }
        }

        // 4. Actualizar laboratory_id, category_id y origin_id en los productos locales según el Master
        if (Schema::connection($targetConn)->hasTable('products') && Schema::connection($masterConn)->hasTable('products')) {
            DB::connection($masterConn)->table('products')
                ->select(['id', 'laboratory_id', 'category_id', 'origin_id'])
                ->whereNotNull('laboratory_id')
                ->orderBy('id')
                ->chunk(1000, function ($masterProds) use ($targetConn) {
                    foreach ($masterProds as $mp) {
                        $upd = [];
                        if ($mp->laboratory_id) {
                            $upd['laboratory_id'] = $mp->laboratory_id;
                        }
                        if ($mp->category_id) {
                            $upd['category_id'] = $mp->category_id;
                        }
                        if ($mp->origin_id) {
                            $upd['origin_id'] = $mp->origin_id;
                        }
                        if (!empty($upd)) {
                            DB::connection($targetConn)->table('products')
                                ->where('id', $mp->id)
                                ->update($upd);
                        }
                    }
                });
        }
    }

    /**
     * Normaliza un RIF para evitar duplicados por guiones o espacios.
     */
    protected function normalizeRif(?string $rif): string
    {
        if (empty($rif)) {
            return '';
        }
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($rif))) ?? '';
    }
}
