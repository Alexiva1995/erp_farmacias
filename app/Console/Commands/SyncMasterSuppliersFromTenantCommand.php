<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\MasterSupplierSeederService;
use Illuminate\Console\Command;

class SyncMasterSuppliersFromTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:sync-master-from-tenant 
                            {--source= : ID o nombre del tenant origen (ej: barrio_sucre o dejar vacío para BD actual)}
                            {--all : Consolidar de forma acumulativa los proveedores y estructuras de TODOS los tenants hacia el Master}
                            {--propagate : Si se debe propagar inmediatamente a todos los tenants tras consolidar el Master}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extrae los proveedores y mapeos de Excel/conexiones de uno o TODOS los tenants hacia tovaerp_master, y opcionalmente los propaga a los demás';

    /**
     * Execute the console command.
     */
    public function handle(MasterSupplierSeederService $seederService): int
    {
        $source = $this->option('source') ? (string) $this->option('source') : null;
        $syncAll = (bool) $this->option('all');
        $shouldPropagate = (bool) $this->option('propagate');

        $this->info('========================================================================');
        $this->info(' SINCRONIZACIÓN DE PROVEEDORES Y ESTRUCTURAS: TENANTS -> MASTER');
        $this->info('========================================================================');

        if ($syncAll) {
            $this->line('• Modo: <fg=yellow>CONSOLIDACIÓN MASIVA DESDE TODOS LOS TENANTS REGISTRADOS</>');
        } elseif ($source) {
            $this->line("• Fuente especificada: <fg=cyan>{$source}</>");
        } else {
            $this->line('• Fuente: <fg=cyan>Base de datos activa / Farmacia de referencia</>');
        }

        $this->line('• Destino: <fg=green>tovaerp_master (Catálogo Maestro Central)</>');
        $this->newLine();

        $this->comment('1. Extrayendo proveedores y mapeos de columnas Excel hacia tovaerp_master...');

        try {
            if ($syncAll) {
                $stats = $seederService->syncMasterFromAllTenants(true);
                $this->line("  ✓ Bases/Tenants procesados: <fg=green>{$stats['tenants_processed']}</>");
            } else {
                $stats = $seederService->syncMasterFromTenant($source, true);
            }

            $this->line("  ✓ Total de proveedores procesados hacia el Master: <fg=green>{$stats['suppliers_synced']}</>");
            $this->line("  ✓ Total de estructuras técnicas consolidadas: <fg=green>{$stats['connections_synced']}</>");
            $this->newLine();

            if ($shouldPropagate) {
                $this->comment('2. Propagando catálogo oficial del Master hacia todos los tenants...');
                $propagationResults = $seederService->propagateToAllTenants();

                $totalTenants = count($propagationResults);
                $successfulTenants = count(array_filter($propagationResults, fn ($count) => $count >= 0));

                $this->line("  ✓ Tenants actualizados: <fg=green>{$successfulTenants}/{$totalTenants}</>");
                foreach ($propagationResults as $tenantId => $count) {
                    if ($count >= 0) {
                        $this->line("    • Tenant [{$tenantId}]: {$count} proveedores actualizados.");
                    } else {
                        $this->line("    • Tenant [{$tenantId}]: <fg=red>Error al actualizar</>");
                    }
                }
            } else {
                $this->info('Nota: Para propagar este catálogo consolidado a todos los tenants, ejecute:');
                $this->line('  php artisan suppliers:sync-tenants');
                $this->line('  o ejecute este comando con el flag: --propagate');
            }

            $this->newLine();
            $this->info('✓ Proceso de sincronización finalizado exitosamente.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error durante la sincronización: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
