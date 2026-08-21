<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReorderOriginIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'origins:reorder-ids 
                            {--start=1 : El ID inicial desde el cual renumerar} 
                            {--dry-run : Ejecutar en modo simulación sin guardar cambios} 
                            {--force : Forzar la ejecución sin confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reorganiza secuencialmente los IDs de todos los orígenes y actualiza la tabla de productos sin colisiones.';

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

        $totalOrigins = DB::table('origins')->count();
        if ($totalOrigins === 0) {
            $this->warn('No hay orígenes en la base de datos para reorganizar.');
            return self::SUCCESS;
        }

        $endId = $startId + $totalOrigins - 1;

        $this->info("=========================================================");
        $this->info(" REORGANIZACIÓN DE IDs DE ORÍGENES");
        $this->info("=========================================================");
        $this->line("• Total de orígenes: <fg=yellow>{$totalOrigins}</>");
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
            DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_origin_id_map');
            DB::statement('
                CREATE TEMPORARY TABLE temp_origin_id_map (
                    old_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                    new_id BIGINT UNSIGNED NOT NULL UNIQUE
                ) ENGINE=InnoDB
            ');

            // 2. Poblar mapeo correlativo old_id -> new_id
            $this->line("2. Generando mapeo correlativo desde {$startId}...");
            $origins = DB::table('origins')
                ->select('id')
                ->orderBy('id', 'asc')
                ->get();

            $currentNewId = $startId;
            $insertData = [];
            foreach ($origins as $origin) {
                $insertData[] = [
                    'old_id' => $origin->id,
                    'new_id' => $currentNewId++,
                ];
            }

            if (!empty($insertData)) {
                DB::table('temp_origin_id_map')->insert($insertData);
            }

            // 3. Desactivar Foreign Key Checks temporalmente
            $this->line('3. Desactivando FOREIGN_KEY_CHECKS...');
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            // 4. Actualizar tabla products.origin_id en dos fases
            $this->line('4. Desplazando origin_id en tabla products...');
            DB::update("
                UPDATE `products` p
                INNER JOIN temp_origin_id_map m ON p.origin_id = m.old_id
                SET p.origin_id = m.new_id + {$offset}
            ");

            DB::update("
                UPDATE `products` p
                SET p.origin_id = p.origin_id - {$offset}
                WHERE p.origin_id >= {$offset}
            ");

            // 5. Actualizar la tabla principal `origins.id` en dos fases sin colisiones
            $this->line('5. Actualizando clave primaria en tabla `origins`...');
            DB::update("
                UPDATE `origins` o
                INNER JOIN temp_origin_id_map m ON o.id = m.old_id
                SET o.id = m.new_id + {$offset}
            ");

            $affectedOrigins = DB::update("
                UPDATE `origins` o
                SET o.id = o.id - {$offset}
                WHERE o.id >= {$offset}
            ");
            $this->line("   • Registros de orígenes actualizados: <fg=green>{$affectedOrigins}</>");

            // 6. Reactivar Foreign Key Checks
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            // 7. Verificación de Integridad Referencial
            $this->line('6. Verificando integridad referencial...');
            $orphanProducts = DB::table('products as p')
                ->leftJoin('origins as o', 'p.origin_id', '=', 'o.id')
                ->whereNotNull('p.origin_id')
                ->whereNull('o.id')
                ->count();

            if ($orphanProducts > 0) {
                throw new \Exception("Inconsistencia detectada: {$orphanProducts} productos con origen huérfano.");
            }

            $this->info("   ✓ Integridad referencial 100% verificada sin registros huérfanos.");

            // 8. Finalización y Commit
            if ($isDryRun) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $this->warn('>> SIMULACIÓN COMPLETADA (Dry-Run): Todos los cambios fueron revertidos exitosamente sin alterar la base de datos.');
            } else {
                if (DB::transactionLevel() > 0) {
                    DB::commit();
                }
                
                // 9. Ajustar AUTO_INCREMENT para nuevos orígenes después del commit
                $nextAutoIncrement = $endId + 1;
                $this->line("7. Ajustando AUTO_INCREMENT a {$nextAutoIncrement}...");
                DB::statement("ALTER TABLE `origins` AUTO_INCREMENT = {$nextAutoIncrement}");

                $this->info('>> OPERACIÓN COMPLETADA CON ÉXITO: Todos los IDs de orígenes y dependencias fueron actualizados.');
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
            Log::error('Error en ReorderOriginIdsCommand: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
