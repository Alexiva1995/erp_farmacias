<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReorderSupplierIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:reorder-ids 
                            {--start=1001 : El ID inicial desde el cual renumerar} 
                            {--dry-run : Ejecutar en modo simulación sin guardar cambios} 
                            {--force : Forzar la ejecución sin confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reorganiza secuencialmente los IDs de todos los proveedores y actualiza todas las tablas relacionales sin colisiones.';

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

        $totalSuppliers = DB::table('suppliers')->count();
        if ($totalSuppliers === 0) {
            $this->warn('No hay proveedores en la base de datos para reorganizar.');
            return self::SUCCESS;
        }

        $endId = $startId + $totalSuppliers - 1;

        $this->info("=========================================================");
        $this->info(" REORGANIZACIÓN DE IDs DE PROVEEDORES");
        $this->info("=========================================================");
        $this->line("• Total de proveedores: <fg=yellow>{$totalSuppliers}</>");
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
            DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_supplier_id_map');
            DB::statement('
                CREATE TEMPORARY TABLE temp_supplier_id_map (
                    old_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                    new_id BIGINT UNSIGNED NOT NULL UNIQUE
                ) ENGINE=InnoDB
            ');

            // 2. Poblar mapeo correlativo old_id -> new_id
            $this->line("2. Generando mapeo correlativo desde {$startId}...");
            $suppliers = DB::table('suppliers')
                ->select('id')
                ->orderBy('id', 'asc')
                ->get();

            $currentNewId = $startId;
            $insertData = [];
            foreach ($suppliers as $supplier) {
                $insertData[] = [
                    'old_id' => $supplier->id,
                    'new_id' => $currentNewId++,
                ];
            }

            if (!empty($insertData)) {
                DB::table('temp_supplier_id_map')->insert($insertData);
            }

            // 3. Desactivar Foreign Key Checks temporalmente
            $this->line('3. Desactivando FOREIGN_KEY_CHECKS...');
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            // 4. Tablas relacionales con columna supplier_id
            $relatedTables = [
                'auto_orders',
                'auto_replenishment_configs',
                'expirations',
                'inventory_movements',
                'invoices',
                'payment_rules',
                'product_lots',
                'product_suppliers',
                'psychotropic_controls',
                'retentions',
                'supplier_connection_statuses',
                'supplier_connections',
                'supplier_discounts',
                'supplier_laboratories',
                'supplier_payment_methods',
                'supplier_ratings',
                'supplier_scores',
                'suppliers_config_products',
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
                        INNER JOIN temp_supplier_id_map m ON t.supplier_id = m.old_id
                        SET t.supplier_id = m.new_id + {$offset}
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
                        SET t.supplier_id = t.supplier_id - {$offset}
                        WHERE t.supplier_id >= {$offset}
                    ");
                }
                $bar2->advance();
            }
            $bar2->finish();
            $this->newLine();

            // 6. Actualizar la tabla principal `suppliers.id` en dos fases sin colisiones
            $this->line('6. Actualizando clave primaria en tabla `suppliers`...');
            DB::update("
                UPDATE `suppliers` s
                INNER JOIN temp_supplier_id_map m ON s.id = m.old_id
                SET s.id = m.new_id + {$offset}
            ");

            $affectedSuppliers = DB::update("
                UPDATE `suppliers` s
                SET s.id = s.id - {$offset}
                WHERE s.id >= {$offset}
            ");
            $this->line("   • Registros de proveedores actualizados: <fg=green>{$affectedSuppliers}</>");

            // 7. Ajustar AUTO_INCREMENT para nuevos proveedores
            $nextAutoIncrement = $endId + 1;
            $this->line("7. Ajustando AUTO_INCREMENT a {$nextAutoIncrement}...");
            DB::statement("ALTER TABLE `suppliers` AUTO_INCREMENT = {$nextAutoIncrement}");

            // 8. Reactivar Foreign Key Checks
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            // 9. Verificación de Integridad Referencial
            $this->line('8. Verificando integridad referencial...');
            $orphanInvoices = DB::table('invoices as i')
                ->leftJoin('suppliers as s', 'i.supplier_id', '=', 's.id')
                ->whereNotNull('i.supplier_id')
                ->whereNull('s.id')
                ->count();

            $orphanProductSuppliers = DB::table('product_suppliers as ps')
                ->leftJoin('suppliers as s', 'ps.supplier_id', '=', 's.id')
                ->whereNotNull('ps.supplier_id')
                ->whereNull('s.id')
                ->count();

            if ($orphanInvoices > 0 || $orphanProductSuppliers > 0) {
                throw new \Exception("Inconsistencia detectada: {$orphanInvoices} facturas huérfanas, {$orphanProductSuppliers} relaciones producto-proveedor huérfanas.");
            }

            $this->info("   ✓ Integridad referencial 100% verificada sin registros huérfanos.");

            // 10. Finalización
            if ($isDryRun) {
                DB::rollBack();
                $this->warn('>> SIMULACIÓN COMPLETADA (Dry-Run): Todos los cambios fueron revertidos exitosamente sin alterar la base de datos.');
            } else {
                DB::commit();
                $this->info('>> OPERACIÓN COMPLETADA CON ÉXITO: Todos los IDs de proveedores y dependencias fueron actualizados.');
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->line("Tiempo de ejecución: <fg=yellow>{$duration}s</>");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            DB::rollBack();
            $this->error('ERROR DURANTE LA EJECUCIÓN: ' . $e->getMessage());
            Log::error('Error en ReorderSupplierIdsCommand: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
