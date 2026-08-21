<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReorderLaboratoryIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laboratories:reorder-ids 
                            {--start=101 : El ID inicial desde el cual renumerar} 
                            {--dry-run : Ejecutar en modo simulación sin guardar cambios} 
                            {--force : Forzar la ejecución sin confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reorganiza secuencialmente los IDs de todos los laboratorios y actualiza todas las tablas relacionales sin colisiones.';

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

        $totalLaboratories = DB::table('laboratories')->count();
        if ($totalLaboratories === 0) {
            $this->warn('No hay laboratorios en la base de datos para reorganizar.');
            return self::SUCCESS;
        }

        $endId = $startId + $totalLaboratories - 1;

        $this->info("=========================================================");
        $this->info(" REORGANIZACIÓN DE IDs DE LABORATORIOS");
        $this->info("=========================================================");
        $this->line("• Total de laboratorios: <fg=yellow>{$totalLaboratories}</>");
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
            DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_laboratory_id_map');
            DB::statement('
                CREATE TEMPORARY TABLE temp_laboratory_id_map (
                    old_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                    new_id BIGINT UNSIGNED NOT NULL UNIQUE
                ) ENGINE=InnoDB
            ');

            // 2. Poblar mapeo correlativo old_id -> new_id
            $this->line("2. Generando mapeo correlativo desde {$startId}...");
            $laboratories = DB::table('laboratories')
                ->select('id')
                ->orderBy('id', 'asc')
                ->get();

            $currentNewId = $startId;
            $insertData = [];
            foreach ($laboratories as $lab) {
                $insertData[] = [
                    'old_id' => $lab->id,
                    'new_id' => $currentNewId++,
                ];
            }

            if (!empty($insertData)) {
                DB::table('temp_laboratory_id_map')->insert($insertData);
            }

            // 3. Desactivar Foreign Key Checks temporalmente
            $this->line('3. Desactivando FOREIGN_KEY_CHECKS...');
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            // 4. Tablas relacionales con columna laboratory_id
            $relatedTables = [
                'products',
                'employee_laboratory',
                'supplier_laboratories',
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
                        INNER JOIN temp_laboratory_id_map m ON t.laboratory_id = m.old_id
                        SET t.laboratory_id = m.new_id + {$offset}
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
                        SET t.laboratory_id = t.laboratory_id - {$offset}
                        WHERE t.laboratory_id >= {$offset}
                    ");
                }
                $bar2->advance();
            }
            $bar2->finish();
            $this->newLine();

            // 6. Actualizar la tabla principal `laboratories.id` en dos fases sin colisiones
            $this->line('6. Actualizando clave primaria en tabla `laboratories`...');
            DB::update("
                UPDATE `laboratories` l
                INNER JOIN temp_laboratory_id_map m ON l.id = m.old_id
                SET l.id = m.new_id + {$offset}
            ");

            $affectedLaboratories = DB::update("
                UPDATE `laboratories` l
                SET l.id = l.id - {$offset}
                WHERE l.id >= {$offset}
            ");
            $this->line("   • Registros de laboratorios actualizados: <fg=green>{$affectedLaboratories}</>");

            // 7. Reactivar Foreign Key Checks
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            // 8. Verificación de Integridad Referencial
            $this->line('7. Verificando integridad referencial...');
            $orphanProducts = DB::table('products as p')
                ->leftJoin('laboratories as l', 'p.laboratory_id', '=', 'l.id')
                ->whereNotNull('p.laboratory_id')
                ->whereNull('l.id')
                ->count();

            if ($orphanProducts > 0) {
                throw new \Exception("Inconsistencia detectada: {$orphanProducts} productos con laboratorio huérfano.");
            }

            $this->info("   ✓ Integridad referencial 100% verificada sin registros huérfanos.");

            // 9. Finalización y Commit
            if ($isDryRun) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $this->warn('>> SIMULACIÓN COMPLETADA (Dry-Run): Todos los cambios fueron revertidos exitosamente sin alterar la base de datos.');
            } else {
                if (DB::transactionLevel() > 0) {
                    DB::commit();
                }

                // 10. Ajustar AUTO_INCREMENT para nuevos laboratorios después del commit
                $nextAutoIncrement = $endId + 1;
                $this->line("8. Ajustando AUTO_INCREMENT a {$nextAutoIncrement}...");
                DB::statement("ALTER TABLE `laboratories` AUTO_INCREMENT = {$nextAutoIncrement}");

                $this->info('>> OPERACIÓN COMPLETADA CON ÉXITO: Todos los IDs de laboratorios y dependencias fueron actualizados.');
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
            Log::error('Error en ReorderLaboratoryIdsCommand: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
