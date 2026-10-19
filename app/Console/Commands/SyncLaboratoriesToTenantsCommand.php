<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\LaboratorySyncService;
use Illuminate\Console\Command;

class SyncLaboratoriesToTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laboratories:sync-tenants 
                            {--tenant= : ID específico de un tenant para sincronizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copia y sincroniza laboratorios y sus grupos desde la base de datos master hacia todos los tenants';

    /**
     * Execute the console command.
     */
    public function handle(LaboratorySyncService $syncService): int
    {
        $tenantId = $this->option('tenant') ? (string) $this->option('tenant') : null;

        $this->info("=========================================================");
        $this->info(" SINCRONIZACIÓN DE LABORATORIOS (MASTER -> TENANTS)");
        $this->info("=========================================================");

        $masterLabs = $syncService->getMasterLaboratories();
        $totalMaster = count($masterLabs);

        if ($totalMaster === 0) {
            $this->warn('No se encontraron registros de laboratorios en la base de datos master.');
            return self::SUCCESS;
        }

        $this->line("• Total de laboratorios en master: <fg=green>{$totalMaster}</>");
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
                $this->line(" [✓] Tenant <fg=yellow>{$id}</>: <fg=green>{$count}</> laboratorios sincronizados");
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
