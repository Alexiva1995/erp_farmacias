<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Suppliers\SupplierEmailCatalogService;
use Illuminate\Console\Command;

class SyncEmailSupplierCatalogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supplier:sync-email-catalogs {--supplier= : ID o nombre del proveedor a sincronizar} {--dry-run : Simula la lectura sin marcar los correos como leídos ni procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revisa la bandeja de Gmail vía IMAP para descargar y procesar automáticamente listas de productos en Excel';

    /**
     * Execute the console command.
     */
    public function handle(SupplierEmailCatalogService $emailCatalogService): int
    {
        $this->info('📧 Iniciando sincronización de catálogos desde Gmail...');

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Modo DRY-RUN activo: no se marcarán correos como leídos ni se modificarán datos.');
        }

        $targetSupplier = null;
        if ($supplierOpt = $this->option('supplier')) {
            $targetSupplier = is_numeric($supplierOpt)
                ? \App\Models\Supplier::find($supplierOpt)
                : \App\Models\Supplier::where('name', 'LIKE', "%{$supplierOpt}%")->first();

            if (!$targetSupplier) {
                $this->error("No se encontró el proveedor '{$supplierOpt}'.");
                return self::FAILURE;
            }

            $this->info("🎯 Proveedor seleccionado: {$targetSupplier->name} (ID: {$targetSupplier->id})");
        }

        try {
            $result = $emailCatalogService->syncEmailCatalogs($dryRun, $targetSupplier);

            $this->info("Archivos/Catálogos detectados: " . count($result['processed']));
            $this->info("Proveedores/Correos revisados: " . (count($result['processed']) + count($result['skipped']) + count($result['errors'])));

            if (!empty($result['processed'])) {
                $totalProductsImported = array_sum(array_column($result['processed'], 'products_count'));

                $this->table(
                    ['Proveedor', 'Archivo', 'Formato', 'Productos', 'De', 'Asunto'],
                    array_map(fn($p) => [
                        $p['supplier_name'] ?? 'N/A',
                        $p['filename'] ?? 'N/A',
                        $p['format_used'] ?? 'Formato 1',
                        isset($p['products_count']) ? ($p['products_count'] > 0 ? "{$p['products_count']} prods" : "0 (Revisar Mapeo)") : 'N/A',
                        $p['from'] ?? 'N/A',
                        $p['subject'] ?? 'N/A',
                    ], $result['processed'])
                );

                if (!$dryRun) {
                    $this->info("📦 Total productos consolidados en catálogo: {$totalProductsImported}");
                }
            }

            if (!empty($result['skipped'])) {
                $this->line('');
                $this->warn('Detalle de proveedores/correos omitidos:');
                foreach ($result['skipped'] as $s) {
                    $prov = $s['supplier_name'] ?? 'Proveedor';
                    $motivo = $s['reason'] ?? 'Sin detalle';
                    $this->line(" - {$prov}: {$motivo}");
                }
            }

            if (!empty($result['errors'])) {
                $this->line('');
                $this->error('Errores encontrados:');
                foreach ($result['errors'] as $e) {
                    $this->line(" - " . ($e['error'] ?? 'Error desconocido'));
                }
            }

            $this->info('✨ Sincronización completada exitosamente.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Error al sincronizar catálogos de correo: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}