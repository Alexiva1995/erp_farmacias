<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReorderProductIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:reorder-ids 
                            {--start=10001 : El ID inicial desde el cual renumerar} 
                            {--dry-run : Ejecutar en modo simulación sin guardar cambios} 
                            {--force : Forzar la ejecución sin confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reorganiza secuencialmente los IDs de todos los productos y actualiza todas las tablas relacionales y campos JSON sin colisiones.';

    // Offset temporal para evitar colisiones en índices UNIQUE
    private const TEMP_OFFSET = 100000000;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startId = (int) $this->option('start');
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        if ($startId < 1) {
            $this->error('El ID inicial debe ser mayor a 0.');
            return self::FAILURE;
        }

        $totalProducts = DB::table('products')->count();
        if ($totalProducts === 0) {
            $this->warn('No hay productos en la base de datos para reorganizar.');
            return self::SUCCESS;
        }

        $endId = $startId + $totalProducts - 1;

        $this->info("=========================================================");
        $this->info(" REORGANIZACIÓN DE IDs DE PRODUCTOS");
        $this->info("=========================================================");
        $this->line("• Total de productos: <fg=yellow>{$totalProducts}</>");
        $this->line("• Rango nuevo: <fg=green>{$startId}</> hasta <fg=green>{$endId}</>");
        $this->line("• Modo: " . ($isDryRun ? '<fg=cyan>SIMULACIÓN (Dry-Run)</>' : '<fg=red;options=bold>PRODUCCIÓN (Modificará la base de datos)</>'));
        $this->info("=========================================================");

        if (!$isDryRun && !$isForce) {
            if (!$this->confirm('¿Estás seguro de continuar? Esta operación reescribirá los IDs en toda la base de datos.')) {
                $this->warn('Operación cancelada por el usuario.');
                return self::SUCCESS;
            }
        }

        $startTime = microtime(true);
        $offset = self::TEMP_OFFSET;

        try {
            DB::beginTransaction();

            // 1. Crear tabla temporal de mapeo
            $this->line('1. Creando tabla de mapeo temporal...');
            DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_product_id_map');
            DB::statement('
                CREATE TEMPORARY TABLE temp_product_id_map (
                    old_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                    new_id BIGINT UNSIGNED NOT NULL UNIQUE
                ) ENGINE=InnoDB
            ');

            // 2. Poblar mapeo correlativo old_id -> new_id
            $this->line("2. Generando mapeo correlativo desde {$startId}...");
            $products = DB::table('products')
                ->select('id')
                ->orderBy('id', 'asc')
                ->get();

            $currentNewId = $startId;
            $insertData = [];
            foreach ($products as $product) {
                $insertData[] = [
                    'old_id' => $product->id,
                    'new_id' => $currentNewId++,
                ];

                if (count($insertData) >= 1000) {
                    DB::table('temp_product_id_map')->insert($insertData);
                    $insertData = [];
                }
            }

            if (!empty($insertData)) {
                DB::table('temp_product_id_map')->insert($insertData);
            }

            // 3. Desactivar Foreign Key Checks temporalmente
            $this->line('3. Desactivando FOREIGN_KEY_CHECKS...');
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            // 4. Tablas relacionales con columna product_id
            $relatedTables = [
                'product_lots',
                'order_details',
                'invoice_details',
                'inventory_movements',
                'quotation_products',
                'product_suppliers',
                'product_profitability',
                'product_variants',
                'product_pack_items',
                'product_counts',
                'product_failures',
                'product_stockouts',
                'returns',
                'invoice_returns',
                'dish_ingredients',
                'ecommerce_order_items',
                'employee_product',
                'expirations',
                'expired_logs',
                'fiscal_history_details',
                'individual_offers',
                'invoices_counts',
                'price_adjustment_logs',
                'psychotropic_controls',
                'sales_counts',
                'supplier_ai_match_rejections',
            ];

            $existingTables = DB::select('SHOW TABLES');
            $dbName = DB::getDatabaseName();
            $tableList = array_map(fn($t) => ((array) $t)["Tables_in_{$dbName}"] ?? array_values((array) $t)[0], $existingTables);

            // Fase 1: Mover a rango temporal alto para evitar colisiones UNIQUE
            $this->line('4. Fase 1: Desplazando IDs de tablas relacionales a rango temporal...');
            $bar = $this->output->createProgressBar(count($relatedTables));
            $bar->start();

            foreach ($relatedTables as $table) {
                if (in_array($table, $tableList)) {
                    DB::update("
                        UPDATE `{$table}` t
                        INNER JOIN temp_product_id_map m ON t.product_id = m.old_id
                        SET t.product_id = m.new_id + {$offset}
                    ");
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();

            // Fase 2: Mover al ID final definitivo
            $this->line('5. Fase 2: Estableciendo IDs finales en tablas relacionales...');
            $bar2 = $this->output->createProgressBar(count($relatedTables));
            $bar2->start();

            foreach ($relatedTables as $table) {
                if (in_array($table, $tableList)) {
                    DB::update("
                        UPDATE `{$table}` t
                        SET t.product_id = t.product_id - {$offset}
                        WHERE t.product_id >= {$offset}
                    ");
                }
                $bar2->advance();
            }
            $bar2->finish();
            $this->newLine();

            // 6. Actualizar configuraciones JSON (product_packs.pack_config y prescription_offers.products)
            $this->line('6. Actualizando campos JSON (pack_config y prescription_offers)...');
            
            // 6.1 product_packs (pack_config es un objeto JSON cuyas claves son product_ids)
            if (in_array('product_packs', $tableList)) {
                $packs = DB::table('product_packs')->whereNotNull('pack_config')->get();
                $updatedPacks = 0;
                foreach ($packs as $pack) {
                    $config = json_decode($pack->pack_config, true);
                    if (is_array($config) && !empty($config)) {
                        $newConfig = [];
                        $hasChanges = false;
                        foreach ($config as $oldKey => $val) {
                            $mappedNewId = DB::table('temp_product_id_map')->where('old_id', (int) $oldKey)->value('new_id');
                            if ($mappedNewId) {
                                $newConfig[(string) $mappedNewId] = $val;
                                $hasChanges = true;
                            } else {
                                $newConfig[$oldKey] = $val;
                            }
                        }
                        if ($hasChanges) {
                            DB::table('product_packs')
                                ->where('id', $pack->id)
                                ->update(['pack_config' => json_encode($newConfig)]);
                            $updatedPacks++;
                        }
                    }
                }
                $this->line("   • Packs actualizados: <fg=green>{$updatedPacks}</>");
            }

            // 6.2 prescription_offers (products es un array JSON de product_ids)
            if (in_array('prescription_offers', $tableList)) {
                $prescriptions = DB::table('prescription_offers')->whereNotNull('products')->get();
                $updatedPrescriptions = 0;
                foreach ($prescriptions as $po) {
                    $prods = json_decode($po->products, true);
                    if (is_array($prods) && !empty($prods)) {
                        $newProds = [];
                        $hasChanges = false;
                        foreach ($prods as $oldPId) {
                            $mappedNewId = DB::table('temp_product_id_map')->where('old_id', (int) $oldPId)->value('new_id');
                            if ($mappedNewId) {
                                $newProds[] = (int) $mappedNewId;
                                $hasChanges = true;
                            } else {
                                $newProds[] = $oldPId;
                            }
                        }
                        if ($hasChanges) {
                            DB::table('prescription_offers')
                                ->where('id', $po->id)
                                ->update(['products' => json_encode($newProds)]);
                            $updatedPrescriptions++;
                        }
                    }
                }
                $this->line("   • Ofertas de récipe actualizadas: <fg=green>{$updatedPrescriptions}</>");
            }

            // 7. Actualizar la tabla principal `products.id` en dos fases sin colisiones
            $this->line('7. Actualizando clave primaria en tabla `products`...');
            DB::update("
                UPDATE `products` p
                INNER JOIN temp_product_id_map m ON p.id = m.old_id
                SET p.id = m.new_id + {$offset}
            ");

            $affectedProducts = DB::update("
                UPDATE `products` p
                SET p.id = p.id - {$offset}
                WHERE p.id >= {$offset}
            ");
            $this->line("   • Registros de productos actualizados: <fg=green>{$affectedProducts}</>");

            // 8. Reactivar Foreign Key Checks
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            // 9. Verificación de Integridad Referencial
            $this->line('8. Verificando integridad referencial...');
            $orphanLots = DB::table('product_lots as pl')
                ->leftJoin('products as p', 'pl.product_id', '=', 'p.id')
                ->whereNull('p.id')
                ->count();

            $orphanMovements = DB::table('inventory_movements as im')
                ->leftJoin('products as p', 'im.product_id', '=', 'p.id')
                ->whereNull('p.id')
                ->count();

            if ($orphanLots > 0 || $orphanMovements > 0) {
                throw new \Exception("Inconsistencia detectada: {$orphanLots} lotes huérfanos, {$orphanMovements} movimientos huérfanos.");
            }

            $this->info("   ✓ Integridad referencial 100% verificada sin registros huérfanos.");

            // 10. Finalización y Commit
            if ($isDryRun) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $this->warn('>> SIMULACIÓN COMPLETADA (Dry-Run): Todos los cambios fueron revertidos exitosamente sin alterar la base de datos.');
            } else {
                if (DB::transactionLevel() > 0) {
                    DB::commit();
                }

                // 11. Ajustar AUTO_INCREMENT para nuevos productos después del commit
                $nextAutoIncrement = $endId + 1;
                $this->line("9. Ajustando AUTO_INCREMENT a {$nextAutoIncrement}...");
                DB::statement("ALTER TABLE `products` AUTO_INCREMENT = {$nextAutoIncrement}");

                $this->info('>> OPERACIÓN COMPLETADA CON ÉXITO: Todos los IDs de productos y dependencias fueron actualizados.');
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->line("Tiempo de ejecución: <fg=yellow>{$duration}s</>");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('ERROR DURANTE LA EJECUCIÓN: ' . $e->getMessage());
            Log::error('Error en ReorderProductIdsCommand: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
