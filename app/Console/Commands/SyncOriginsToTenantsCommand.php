<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\OriginSyncService;
use Illuminate\Console\Command;

class SyncOriginsToTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'origins:sync-tenants 
                            {--tenant= : ID específico de un tenant para sincronizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copia y sincroniza los orígenes desde la base de datos master hacia todos los tenants';

    /**
     * Execute the console command.
     */
    public function handle(OriginSyncService $syncService): int
    {
        $tenantId = $this->option('tenant') ? (string) $this->option('tenant') : null;

        $this->info("=========================================================");
        $this->info(" SINCRONIZACIÓN DE ORÍGENES (MASTER -> TENANTS)");
        $this->info("=========================================================");

        $masterOrigins = $syncService->getMasterOrigins();
        $totalMaster = count($masterOrigins);

        if ($totalMaster === 0) {
            $this->warn('No se encontraron registros de orígenes en la base de datos master.');
            return self::SUCCESS;
        }

        $this->line("• Total de orígenes en master: <fg=green>{$totalMaster}</>");
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

        $rows = [];
        $hasErrors = false;

        foreach ($results as $id => $status) {
            if ($status === -1) {
                $hasErrors = true;
                $rows[] = [$id, 'ERROR', '<fg=red>Fallo al sincronizar (ver logs)</>'];
            } else {
                $rows[] = [$id, (string) $status, '<fg=green>Sincronizado</>'];
            }
        }

        $this->table(['Tenant ID', 'Orígenes Sincronizados', 'Estado'], $rows);

        if ($hasErrors) {
            $this->error('La sincronización finalizó con errores en uno o más tenants.');
            return self::FAILURE;
        }

        $this->info('>> OPERACIÓN COMPLETADA CON ÉXITO: Todos los tenants han sido actualizados.');
        return self::SUCCESS;
    }
}
