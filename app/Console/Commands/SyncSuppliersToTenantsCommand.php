<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\SupplierSyncService;
use Illuminate\Console\Command;

class SyncSuppliersToTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:sync-tenants 
                            {--tenant= : ID específico de un tenant para sincronizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copia y sincroniza proveedores desde la base de datos master hacia todos los tenants';

    /**
     * Execute the console command.
     */
    public function handle(SupplierSyncService $syncService): int
    {
        $tenantId = $this->option('tenant') ? (string) $this->option('tenant') : null;

        $this->info("=========================================================");
        $this->info(" SINCRONIZACIÓN DE PROVEEDORES (MASTER -> TENANTS)");
        $this->info("=========================================================");

        $masterSuppliers = $syncService->getMasterSuppliers();
        $totalMaster = count($masterSuppliers);

        if ($totalMaster === 0) {
            $this->warn('No se encontraron registros de proveedores en la base de datos master.');
            return self::SUCCESS;
        }

        $this->line("• Total de proveedores en master: <fg=green>{$totalMaster}</>");
        if ($tenantId !== null) {
            $this->line("• Tenant objetivo: <fg=yellow>{$tenantId}</>");
        } else {
            $this->line("• Destino: <fg=cyan>Todos los tenants registrados</>");
        }
        $this->newLine();

        $results = $syncService->syncAllTenants($tenantId);

        if (empty($results)) {
            $this->warn('No se encontraron tenants para sincronizar.');
            return self::SUCCESS;
        }

        $this->info("---------------------------------------------------------");
        $this->info(" RESULTADOS:");
        $this->info("---------------------------------------------------------");

        $totalSuccess = 0;
        $totalFailed = 0;

        foreach ($results as $id => $count) {
            if ($count >= 0) {
                $this->line(" [✓] Tenant <fg=yellow>{$id}</>: <fg=green>{$count}</> proveedores sincronizados");
                $totalSuccess++;
            } else {
                $this->line(" [✗] Tenant <fg=yellow>{$id}</>: <fg=red>Error al sincronizar</>");
                $totalFailed++;
            }
        }

        $this->newLine();
        $this->info("Resumen: {$totalSuccess} exitosos, {$totalFailed} fallidos.");

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
