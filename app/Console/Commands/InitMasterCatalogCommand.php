<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InitMasterCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'master-catalog:init 
                            {--source-db= : Base de datos origen alternativa (ej: farmaciabs_erp2)}
                            {--clean : Vaciar las tablas del maestro antes de importar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea la estructura de tablas y puebla la base de datos tovaerp_master desde la base central';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $masterConn = 'master_catalog';
        $sourceDb = $this->option('source-db');

        if ($sourceDb) {
            config(['database.connections.source_custom' => array_merge(
                config('database.connections.' . config('tenancy.database.central_connection', 'mysql')),
                ['database' => $sourceDb]
            )]);
            $centralConn = 'source_custom';
            $this->info("• Usando base de datos origen personalizada: <fg=yellow>{$sourceDb}</>");
        } else {
            $centralConn = config('tenancy.database.central_connection', 'mysql');
        }

        $this->info('========================================================================');
        $this->info(' INICIALIZADOR DE CATÁLOGO MAESTRO (tovaerp_master)');
        $this->info('========================================================================');

        try {
            DB::connection($masterConn)->getPdo();
            $this->info("✓ Conexión establecida exitosamente con la base de datos maestra.");
        } catch (\Throwable $e) {
            $this->error("No se pudo conectar a 'master_catalog': " . $e->getMessage());
            $this->warn("Asegúrate de haber creado la base de datos ejecutando en MySQL:");
            $this->line("CREATE DATABASE tovaerp_master CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            return self::FAILURE;
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

        // 1. Crear tablas si no existen en tovaerp_master clonando la estructura de central
        foreach ($tables as $table) {
            if (!Schema::connection($centralConn)->hasTable($table)) {
                $this->warn("• Tabla {$table} no existe en la base central. Omitiendo.");
                continue;
            }

            if (!Schema::connection($masterConn)->hasTable($table)) {
                $this->line("• Creando tabla <fg=cyan>{$table}</> en master...");
                $createSql = DB::connection($centralConn)->select("SHOW CREATE TABLE `{$table}`");
                $prop = 'Create Table';
                $ddl = $createSql[0]->$prop;

                DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::connection($masterConn)->statement($ddl);
                DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->info("  ✓ Creada tabla {$table}.");
            } else {
                $this->line("• Tabla <fg=yellow>{$table}</> ya existe en master.");
            }
        }

        // Si se pide limpiar
        if ($this->option('clean')) {
            $this->warn("Limpiando tablas del maestro...");
            DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=0;');
            foreach (array_reverse($tables) as $table) {
                if (Schema::connection($masterConn)->hasTable($table)) {
                    DB::connection($masterConn)->table($table)->truncate();
                }
            }
            DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=0;');

        // 2. Copiar catálogos base (groups_laboratories, laboratories, categories, origins, groups_products)
        $simpleCatalogs = ['groups_laboratories', 'categories', 'origins', 'groups_products', 'laboratories'];
        foreach ($simpleCatalogs as $catTable) {
            if (!Schema::connection($centralConn)->hasTable($catTable)) {
                continue;
            }

            $countCentral = DB::connection($centralConn)->table($catTable)->count();
            $countMaster = DB::connection($masterConn)->table($catTable)->count();

            if ($countMaster === 0 && $countCentral > 0) {
                $this->line("• Copiando {$countCentral} registros de <fg=cyan>{$catTable}</>...");
                $rows = DB::connection($centralConn)->table($catTable)->get()->map(fn ($r) => (array) $r)->toArray();
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::connection($masterConn)->table($catTable)->insert($chunk);
                }
                $this->info("  ✓ {$catTable} poblada.");
            }
        }

        // 3. Copiar Proveedores (Datos fiscales principales)
        if (Schema::connection($centralConn)->hasTable('suppliers')) {
            $countSupCentral = DB::connection($centralConn)->table('suppliers')->count();
            $countSupMaster = DB::connection($masterConn)->table('suppliers')->count();

            if ($countSupMaster === 0 && $countSupCentral > 0) {
                $this->line("• Copiando {$countSupCentral} proveedores a master...");
                $suppliers = DB::connection($centralConn)->table('suppliers')->get()->map(fn ($r) => (array) $r)->toArray();
                foreach (array_chunk($suppliers, 200) as $chunk) {
                    DB::connection($masterConn)->table('suppliers')->insert($chunk);
                }
                $this->info("  ✓ Proveedores poblados.");
            }
        }

        // 4. Copiar Conexiones de Proveedores (Limpiando username y password en el master)
        if (Schema::connection($centralConn)->hasTable('supplier_connections')) {
            $countConnCentral = DB::connection($centralConn)->table('supplier_connections')->count();
            $countConnMaster = DB::connection($masterConn)->table('supplier_connections')->count();

            if ($countConnMaster === 0 && $countConnCentral > 0) {
                $this->line("• Copiando {$countConnCentral} estructuras de conexiones (sin credenciales)...");
                $connections = DB::connection($centralConn)->table('supplier_connections')->get()->map(function ($r) {
                    $row = (array) $r;
                    $row['username'] = null;
                    $row['password'] = null;
                    $row['path'] = null;
                    $row['invoice_path'] = null;
                    return $row;
                })->toArray();

                foreach (array_chunk($connections, 200) as $chunk) {
                    DB::connection($masterConn)->table('supplier_connections')->insert($chunk);
                }
                $this->info("  ✓ Estructuras de proveedores listas en master.");
            }
        }

        // 5. Copiar Productos (Ficha técnica unificada)
        if (Schema::connection($centralConn)->hasTable('products')) {
            $countProdCentral = DB::connection($centralConn)->table('products')->count();
            $countProdMaster = DB::connection($masterConn)->table('products')->count();

            if ($countProdMaster === 0 && $countProdCentral > 0) {
                $this->line("• Copiando {$countProdCentral} productos a master...");
                $prodCols = Schema::connection($masterConn)->getColumnListing('products');
                $prodColsFlip = array_flip($prodCols);

                DB::connection($centralConn)->table('products')
                    ->orderBy('id')
                    ->chunk(1000, function ($products) use ($masterConn, $prodColsFlip) {
                        $cleaned = [];
                        foreach ($products as $p) {
                            $item = array_intersect_key((array) $p, $prodColsFlip);
                            if (isset($item['is_deleted'])) {
                                $item['is_deleted'] = 0;
                            }
                            if (isset($item['is_active'])) {
                                $item['is_active'] = 1;
                            }
                            if (isset($item['deleted_at'])) {
                                $item['deleted_at'] = null;
                            }
                            $cleaned[] = $item;
                        }
                        DB::connection($masterConn)->table('products')->insert($cleaned);
                    });
                $this->info("  ✓ Catálogo de productos maestro poblado.");
            }
        }

        DB::connection($masterConn)->statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('========================================================================');
        $this->info(' INICIALIZACIÓN COMPLETADA CON ÉXITO');
        $this->info('========================================================================');

        return self::SUCCESS;
    }
}
