<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Catalog\OnboardingLegacyImportService;
use Illuminate\Console\Command;

class ImportLegacyOnboardingCommand extends Command
{
    /**
     * El nombre y firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'catalog:import-legacy-onboarding
                            {--products=Listado de Productos 03-10-2026.xls : Ruta del archivo general de productos}
                            {--lots=Listado de Productos lotes 03-10-2026.xls : Ruta del archivo de lotes}
                            {--no-master : Omitir homologación y registro en el Catálogo Maestro}';

    /**
     * Descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Importa y unifica el catálogo y los lotes del sistema legado con ajuste de stock y homologación en el Catálogo Maestro.';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(OnboardingLegacyImportService $importService): int
    {
        $productsFile = (string) $this->option('products');
        $lotsFile = (string) $this->option('lots');
        $syncWithMaster = !$this->option('no-master');

        // Resolver rutas completas si son relativas
        if (!file_exists($productsFile) && file_exists(base_path($productsFile))) {
            $productsFile = base_path($productsFile);
        }
        if (!file_exists($lotsFile) && file_exists(base_path($lotsFile))) {
            $lotsFile = base_path($lotsFile);
        }

        $this->info('=== INICIANDO IMPORTACIÓN DE ONBOARDING LEGADO ===');
        $this->line("Archivo Productos : <comment>{$productsFile}</comment>");
        $this->line("Archivo Lotes     : <comment>{$lotsFile}</comment>");
        $this->line("Sincronizar Master: <comment>" . ($syncWithMaster ? 'SÍ (farmacias.com)' : 'NO') . "</comment>\n");

        if (!file_exists($productsFile)) {
            $this->error("El archivo de productos no fue encontrado: {$productsFile}");
            return Command::FAILURE;
        }

        if (!file_exists($lotsFile)) {
            $this->error("El archivo de lotes no fue encontrado: {$lotsFile}");
            return Command::FAILURE;
        }

        $progressBar = null;

        try {
            $stats = $importService->import(
                $productsFile,
                $lotsFile,
                $syncWithMaster,
                function (string $phase, int $current, int $total, string $message) use (&$progressBar) {
                    if ($phase === 'importing') {
                        if ($progressBar === null) {
                            $this->line("\nGuardando productos y lotes en la base de datos:");
                            $progressBar = $this->output->createProgressBar($total);
                            $progressBar->start();
                        }
                        $progressBar->setProgress($current);
                    } else {
                        $this->line("<info>[INFO]</info> {$message}");
                    }
                }
            );

            if ($progressBar !== null) {
                $progressBar->finish();
                $this->newLine(2);
            }

            $this->info('=== IMPORTACIÓN COMPLETADA CON ÉXITO ===');
            $this->table(
                ['Métrica', 'Cantidad / Valor'],
                [
                    ['Total Productos Procesados', number_format($stats['total_products'])],
                    ['Productos Creados', number_format($stats['created'])],
                    ['Productos Actualizados', number_format($stats['updated'])],
                    ['Homologados con Master (Existentes)', number_format($stats['matched_master'])],
                    ['Registrados Nuevos en Master', number_format($stats['registered_master'])],
                    ['Total Lotes Creados', number_format($stats['total_lots_created'])],
                    ['Lotes Reducidos por Tope de Stock', number_format($stats['lots_reduced_for_cap'])],
                    ['Lotes Ajustados por Faltante de Stock', number_format($stats['lots_extended_for_shortage'])],
                    ['Stock Total Consolidado', number_format($stats['total_consolidated_stock'], 2)],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("Error durante la importación: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
