<?php

namespace App\Console\Commands;

use App\Services\Catalog\MasterCatalogClientService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HomologateSlaveCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:homologate-slave 
                            {--dry-run : Simular la homologación y consultar al Master sin alterar la base de datos local} 
                            {--force : Ejecutar sin confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Homologa e iguala todos los IDs de laboratorios, orígenes, grupos, proveedores y productos existentes en la esclava con el Catálogo Maestro.';

    private const TEMP_OFFSET = 100000000;

    public function handle(MasterCatalogClientService $masterClient): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        $role = config('catalog.role', 'standalone');
        $masterUrl = config('catalog.master_url');
        $masterKey = config('catalog.master_key');

        if ($role !== 'slave' || empty($masterUrl) || empty($masterKey)) {
            $this->error('Este comando solo puede ejecutarse en instancias configuradas como esclavas (CATALOG_ROLE=slave) con MASTER_API_URL y MASTER_API_KEY configuradas.');
            return self::FAILURE;
        }

        $this->info("=========================================================");
        $this->info(" HOMOLOGACIÓN DE CATÁLOGO ESCLAVO CON MASTER");
        $this->info("=========================================================");
        $this->line("• Master URL: <fg=cyan>{$masterUrl}</>");
        $this->line("• Modo: " . ($isDryRun ? '<fg=cyan>SIMULACIÓN (Dry-Run)</>' : '<fg=red;options=bold>PRODUCCIÓN (Modificará la base de datos)</>'));
        $this->info("=========================================================");

        // 1. Probar conectividad con el Master
        $this->line('1. Probando conectividad con el Servidor Master...');
        try {
            $testRes = Http::timeout(5)
                ->withHeaders(['X-Master-Key' => $masterKey, 'Accept' => 'application/json'])
                ->get("{$masterUrl}/lookup", ['barcode' => 'test-ping']);

            if (!$testRes->successful() && $testRes->status() !== 200 && $testRes->status() !== 404) {
                $this->error("El Master respondió con código HTTP {$testRes->status()}. Verifique la URL y MASTER_API_KEY.");
                return self::FAILURE;
            }
            $this->info('   ✓ Conexión con el Catálogo Maestro establecida exitosamente.');
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar al Catálogo Maestro: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (!$isDryRun && !$isForce) {
            if (!$this->confirm('¿Deseas proceder con la homologación? Se sincronizarán e igualarán los IDs locales con el Master.')) {
                $this->warn('Operación cancelada.');
                return self::SUCCESS;
            }
        }

        $startTime = microtime(true);

        try {
            // ==========================================
            // ETAPA A: HOMOLOGAR LABORATORIOS
            // ==========================================
            $this->info("\n--- [1/5] Homologando Laboratorios ---");
            $labs = DB::table('laboratories')->orderBy('id')->get();
            $labMap = []; // old_id => new_id

            $barLabs = $this->output->createProgressBar(count($labs));
            $barLabs->start();

            foreach ($labs as $lab) {
                if (!empty($lab->name)) {
                    $masterLab = $masterClient->registerLaboratoryInMaster([
                        'name'      => $lab->name,
                        'group_id'  => $lab->group_id ?? null,
                        'parent_id' => $lab->parent_id ?? null,
                    ]);

                    if (!empty($masterLab['id']) && (int) $masterLab['id'] !== (int) $lab->id) {
                        $labMap[$lab->id] = (int) $masterLab['id'];
                    }
                }
                $barLabs->advance();
            }
            $barLabs->finish();
            $this->newLine();
            $this->line('• Laboratorios a remapear: <fg=yellow>' . count($labMap) . '</>');

            // ==========================================
            // ETAPA B: HOMOLOGAR ORÍGENES
            // ==========================================
            $this->info("\n--- [2/5] Homologando Orígenes ---");
            $origins = DB::table('origins')->orderBy('id')->get();
            $originMap = [];

            $barOrigins = $this->output->createProgressBar(count($origins));
            $barOrigins->start();

            foreach ($origins as $origin) {
                if (!empty($origin->name)) {
                    $masterOrigin = $masterClient->registerOriginInMaster([
                        'name' => $origin->name,
                    ]);

                    if (!empty($masterOrigin['id']) && (int) $masterOrigin['id'] !== (int) $origin->id) {
                        $originMap[$origin->id] = (int) $masterOrigin['id'];
                    }
                }
                $barOrigins->advance();
            }
            $barOrigins->finish();
            $this->newLine();
            $this->line('• Orígenes a remapear: <fg=yellow>' . count($originMap) . '</>');

            // ==========================================
            // ETAPA C: HOMOLOGAR GRUPOS
            // ==========================================
            $this->info("\n--- [3/5] Homologando Grupos de Productos ---");
            $groups = DB::table('groups_products')->orderBy('id')->get();
            $groupMap = [];

            $barGroups = $this->output->createProgressBar(count($groups));
            $barGroups->start();

            foreach ($groups as $group) {
                if (!empty($group->name)) {
                    $masterGroup = $masterClient->registerGroupInMaster([
                        'name' => $group->name,
                    ]);

                    if (!empty($masterGroup['id']) && (int) $masterGroup['id'] !== (int) $group->id) {
                        $groupMap[$group->id] = (int) $masterGroup['id'];
                    }
                }
                $barGroups->advance();
            }
            $barGroups->finish();
            $this->newLine();
            $this->line('• Grupos a remapear: <fg=yellow>' . count($groupMap) . '</>');

            // ==========================================
            // ETAPA D: HOMOLOGAR PROVEEDORES
            // ==========================================
            $this->info("\n--- [4/5] Homologando Proveedores ---");
            $suppliers = DB::table('suppliers')->orderBy('id')->get();
            $supplierMap = [];

            $barSuppliers = $this->output->createProgressBar(count($suppliers));
            $barSuppliers->start();

            foreach ($suppliers as $sup) {
                if (!empty($sup->name)) {
                    $masterSup = $masterClient->registerSupplierInMaster([
                        'name'             => $sup->name,
                        'social_reason'    => $sup->social_reason ?? $sup->name,
                        'rif'              => $sup->rif ?? null,
                        'sales_phone'      => $sup->sales_phone ?? null,
                        'collections_phone'=> $sup->collections_phone ?? null,
                        'credit_days'      => $sup->credit_days ?? 0,
                        'payment_method'   => $sup->payment_method ?? 'Bs',
                        'cash_payment'     => (bool) ($sup->cash_payment ?? true),
                        'charges_igtf'     => (bool) ($sup->charges_igtf ?? false),
                    ]);

                    if (!empty($masterSup['id']) && (int) $masterSup['id'] !== (int) $sup->id) {
                        $supplierMap[$sup->id] = (int) $masterSup['id'];
                    }
                }
                $barSuppliers->advance();
            }
            $barSuppliers->finish();
            $this->newLine();
            $this->line('• Proveedores a remapear: <fg=yellow>' . count($supplierMap) . '</>');

            // ==========================================
            // ETAPA E: HOMOLOGAR PRODUCTOS
            // ==========================================
            $this->info("\n--- [5/5] Homologando Productos con el Catálogo Maestro ---");
            
            $productCols = \Illuminate\Support\Facades\Schema::getColumnListing('products');
            $hasLabId = in_array('laboratory_id', $productCols);
            $hasOriginId = in_array('origin_id', $productCols);
            $hasCategoryId = in_array('category_id', $productCols);

            $selects = ['p.id', 'p.name', 'p.barcode'];
            if (in_array('active_ingredient', $productCols)) $selects[] = 'p.active_ingredient';
            if (in_array('category_id', $productCols)) $selects[] = 'p.category_id';
            if (in_array('origin_id', $productCols)) $selects[] = 'p.origin_id';
            if (in_array('unit_cost', $productCols)) $selects[] = 'p.unit_cost';
            if (in_array('sale_price', $productCols)) $selects[] = 'p.sale_price';
            if (in_array('description', $productCols)) $selects[] = 'p.description';
            if (in_array('presentation', $productCols)) $selects[] = 'p.presentation';
            if (in_array('unit_of_measure', $productCols)) $selects[] = 'p.unit_of_measure';
            if (in_array('photo_url', $productCols)) $selects[] = 'p.photo_url';
            if (in_array('psychotropic', $productCols)) $selects[] = 'p.psychotropic';
            if (in_array('iva', $productCols)) $selects[] = 'p.iva';

            $query = DB::table('products as p')->select($selects);

            if ($hasLabId && \Illuminate\Support\Facades\Schema::hasTable('laboratories')) {
                $query->leftJoin('laboratories as l', 'p.laboratory_id', '=', 'l.id')
                      ->addSelect('l.name as laboratory_name');
            }

            if ($hasOriginId && \Illuminate\Support\Facades\Schema::hasTable('origins')) {
                $query->leftJoin('origins as o', 'p.origin_id', '=', 'o.id')
                      ->addSelect('o.name as origin_name');
            }

            if ($hasCategoryId && \Illuminate\Support\Facades\Schema::hasTable('categories')) {
                $query->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
                      ->addSelect('c.name as category_name');
            }

            $products = $query->orderBy('p.id')->get();

            $productMap = [];
            $failedProducts = 0;

            $barProducts = $this->output->createProgressBar(count($products));
            $barProducts->start();

            foreach ($products as $prod) {
                if (!empty($prod->name)) {
                    $masterProd = $masterClient->registerProductInMaster([
                        'name'              => $prod->name,
                        'barcode'           => !empty($prod->barcode) ? $prod->barcode : null,
                        'active_ingredient' => $prod->active_ingredient ?? null,
                        'laboratory_name'   => $prod->laboratory_name ?? null,
                        'origin_name'       => $prod->origin_name ?? null,
                        'category_name'     => $prod->category_name ?? null,
                        'description'       => $prod->description ?? null,
                        'presentation'      => $prod->presentation ?? null,
                        'unit_of_measure'   => $prod->unit_of_measure ?? null,
                        'photo_url'         => $prod->photo_url ?? null,
                        'unit_cost'         => $prod->unit_cost ?? 0,
                        'sale_price'        => $prod->sale_price ?? 0,
                        'psychotropic'      => (bool) ($prod->psychotropic ?? false),
                        'iva'               => $prod->iva ?? 0,
                    ]);

                    if ($masterProd && !empty($masterProd['id'])) {
                        if ((int) $masterProd['id'] !== (int) $prod->id) {
                            $productMap[$prod->id] = (int) $masterProd['id'];
                        }
                    } else {
                        $failedProducts++;
                    }
                }
                $barProducts->advance();
            }
            $barProducts->finish();
            $this->newLine();
            $this->line('• Productos a remapear con nuevo ID oficial: <fg=green>' . count($productMap) . '</>');
            if ($failedProducts > 0) {
                $this->warn("• Productos que no pudieron sincronizarse: {$failedProducts}");
            }

            // ==========================================
            // EJECUCIÓN DE MIGRACIÓN DE IDs EN BASE DE DATOS
            // ==========================================
            $this->info("\n=========================================================");
            $this->info(" APLICANDO REMAPEO EN BASE DE DATOS LOCAL");
            $this->info("=========================================================");

            DB::beginTransaction();
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            $offset = self::TEMP_OFFSET;

            // 1. Remapear Laboratorios
            if (!empty($labMap)) {
                $this->line('• Actualizando tabla `laboratories` y relaciones...');
                $this->applyMapping('laboratories', 'id', $labMap, [
                    'products' => 'laboratory_id',
                    'employee_laboratory' => 'laboratory_id',
                    'supplier_laboratories' => 'laboratory_id',
                ], $offset);
            }

            // 2. Remapear Orígenes
            if (!empty($originMap)) {
                $this->line('• Actualizando tabla `origins` y relaciones...');
                $this->applyMapping('origins', 'id', $originMap, [
                    'products' => 'origin_id',
                ], $offset);
            }

            // 3. Remapear Grupos
            if (!empty($groupMap)) {
                $this->line('• Actualizando tabla `groups_products` y relaciones...');
                $this->applyMapping('groups_products', 'id', $groupMap, [
                    'products' => 'group_id',
                ], $offset);
            }

            // 4. Remapear Proveedores
            if (!empty($supplierMap)) {
                $this->line('• Actualizando tabla `suppliers` y relaciones...');
                $this->applyMapping('suppliers', 'id', $supplierMap, [
                    'auto_orders' => 'supplier_id',
                    'auto_replenishment_configs' => 'supplier_id',
                    'expirations' => 'supplier_id',
                    'inventory_movements' => 'supplier_id',
                    'invoices' => 'supplier_id',
                    'payment_rules' => 'supplier_id',
                    'product_lots' => 'supplier_id',
                    'product_suppliers' => 'supplier_id',
                    'psychotropic_controls' => 'supplier_id',
                    'retentions' => 'supplier_id',
                    'supplier_connection_statuses' => 'supplier_id',
                    'supplier_connections' => 'supplier_id',
                    'supplier_discounts' => 'supplier_id',
                    'supplier_laboratories' => 'supplier_id',
                    'supplier_payment_methods' => 'supplier_id',
                    'supplier_ratings' => 'supplier_id',
                    'supplier_scores' => 'supplier_id',
                    'suppliers_config_products' => 'supplier_id',
                ], $offset);
            }

            // 5. Remapear Productos y sus 26 tablas
            if (!empty($productMap)) {
                $this->line('• Actualizando tabla `products` y sus 26 tablas relacionales...');
                $this->applyMapping('products', 'id', $productMap, [
                    'product_lots' => 'product_id',
                    'order_details' => 'product_id',
                    'invoice_details' => 'product_id',
                    'inventory_movements' => 'product_id',
                    'quotation_products' => 'product_id',
                    'product_suppliers' => 'product_id',
                    'product_profitability' => 'product_id',
                    'product_variants' => 'product_id',
                    'product_pack_items' => 'product_id',
                    'product_counts' => 'product_id',
                    'product_failures' => 'product_id',
                    'product_stockouts' => 'product_id',
                    'returns' => 'product_id',
                    'invoice_returns' => 'product_id',
                    'dish_ingredients' => 'product_id',
                    'ecommerce_order_items' => 'product_id',
                    'employee_product' => 'product_id',
                    'expirations' => 'product_id',
                    'expired_logs' => 'product_id',
                    'fiscal_history_details' => 'product_id',
                    'individual_offers' => 'product_id',
                    'invoices_counts' => 'product_id',
                    'price_adjustment_logs' => 'product_id',
                    'psychotropic_controls' => 'product_id',
                    'sales_counts' => 'product_id',
                    'supplier_ai_match_rejections' => 'product_id',
                ], $offset);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            if ($isDryRun) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $this->warn("\n>> SIMULACIÓN COMPLETADA (Dry-Run): No se guardaron cambios en la base de datos.");
            } else {
                if (DB::transactionLevel() > 0) {
                    DB::commit();
                }
                $this->info("\n>> HOMOLOGACIÓN COMPLETADA CON ÉXITO: Todos los catálogos de la esclava fueron igualados con el Master.");
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->line("Tiempo total: <fg=yellow>{$duration}s</>");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('ERROR DURANTE LA HOMOLOGACIÓN: ' . $e->getMessage());
            Log::error('Error en HomologateSlaveCatalogCommand: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Aplica el remapeo de IDs en 2 fases libre de colisiones.
     */
    private function applyMapping(string $mainTable, string $primaryKey, array $map, array $foreignRelations, int $offset): void
    {
        $existingTables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $tableList = array_map(fn($t) => ((array) $t)["Tables_in_{$dbName}"] ?? array_values((array) $t)[0], $existingTables);

        // Crear tabla temporal de mapeo
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_generic_map');
        DB::statement('
            CREATE TEMPORARY TABLE temp_generic_map (
                old_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                new_id BIGINT UNSIGNED NOT NULL UNIQUE
            ) ENGINE=InnoDB
        ');

        $insertData = [];
        foreach ($map as $oldId => $newId) {
            $insertData[] = ['old_id' => $oldId, 'new_id' => $newId];
            if (count($insertData) >= 500) {
                DB::table('temp_generic_map')->insert($insertData);
                $insertData = [];
            }
        }
        if (!empty($insertData)) {
            DB::table('temp_generic_map')->insert($insertData);
        }

        // Fase 1: Tablas relacionales -> temporal
        foreach ($foreignRelations as $relTable => $fkColumn) {
            if (in_array($relTable, $tableList)) {
                DB::update("
                    UPDATE `{$relTable}` t
                    INNER JOIN temp_generic_map m ON t.{$fkColumn} = m.old_id
                    SET t.{$fkColumn} = m.new_id + {$offset}
                ");
            }
        }

        // Fase 2: Tablas relacionales -> definitivo
        foreach ($foreignRelations as $relTable => $fkColumn) {
            if (in_array($relTable, $tableList)) {
                DB::update("
                    UPDATE `{$relTable}` t
                    SET t.{$fkColumn} = t.{$fkColumn} - {$offset}
                    WHERE t.{$fkColumn} >= {$offset}
                ");
            }
        }

        // Actualizar tabla principal en 2 fases
        DB::update("
            UPDATE `{$mainTable}` p
            INNER JOIN temp_generic_map m ON p.{$primaryKey} = m.old_id
            SET p.{$primaryKey} = m.new_id + {$offset}
        ");

        DB::update("
            UPDATE `{$mainTable}` p
            SET p.{$primaryKey} = p.{$primaryKey} - {$offset}
            WHERE p.{$primaryKey} >= {$offset}
        ");
    }
}
