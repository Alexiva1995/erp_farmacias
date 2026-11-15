<?php

namespace App\Console\Commands;

use App\Services\Products\FarmatodoImageSyncService;
use Illuminate\Console\Command;

class SyncFarmatodoImagesCommand extends Command
{
    /**
     * Firma del comando Artisan.
     */
    protected $signature = 'products:sync-farmatodo-images
                            {--limit=100 : Cantidad máxima de productos a procesar}
                            {--force : Sobrescribir imágenes existentes}
                            {--dry-run : Simular sin descargar ni guardar cambios}';

    /**
     * Descripción del comando.
     */
    protected $description = 'Sincroniza imágenes de productos buscando por código de barras en Farmatodo (Algolia) con protección anti-ban.';

    public function handle(FarmatodoImageSyncService $syncService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Iniciando sincronización (límite: {$limit}, force: " . ($force ? 'sí' : 'no') . ", dry-run: " . ($dryRun ? 'sí' : 'no') . ')...');

        $stats = $syncService->syncMissingImages($limit, $force, $dryRun);

        $this->table(
            ['Procesados', 'Actualizados', 'No encontrados', 'Errores', 'Abortado'],
            [[
                $stats['processed'],
                $stats['updated'],
                $stats['not_found'],
                $stats['errors'],
                $stats['aborted'] ? 'SÍ (bloqueo detectado)' : 'No',
            ]]
        );

        return $stats['aborted'] ? self::FAILURE : self::SUCCESS;
    }
}
