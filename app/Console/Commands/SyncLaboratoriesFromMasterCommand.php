<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Inventory\MasterLaboratorySyncService;
use Illuminate\Console\Command;

class SyncLaboratoriesFromMasterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drogueria:sync-laboratories
                            {--tenant= : ID de un tenant específico para inicializar y ejecutar}
                            {--all-products : Reasignar todos los productos, no solo los que están en Generales}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza laboratorios desde el catálogo central Master y reasigna los productos del tenant a su laboratorio real';

    /**
     * Execute the console command.
     */
    public function handle(MasterLaboratorySyncService $syncService): int
    {
        $tenantId = $this->option('tenant') ? (string) $this->option('tenant') : null;
        $allProducts = (bool) $this->option('all-products');

        $this->info('=========================================================');
        $this->info(' SINCRONIZACIÓN Y REASIGNACIÓN DE LABORATORIOS (MASTER)');
        $this->info('=========================================================');

        if ($tenantId !== null) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("El tenant '{$tenantId}' no fue encontrado en la base central.");
                return self::FAILURE;
            }

            $this->info("Inicializando contexto para el tenant: {$tenantId}");

            return $tenant->run(function () use ($syncService, $allProducts) {
                return $this->executeSync($syncService, $allProducts);
            });
        }

        return $this->executeSync($syncService, $allProducts);
    }

    /**
     * Ejecuta el proceso de sincronización y reasignación en el tenant activo.
     */
    protected function executeSync(MasterLaboratorySyncService $syncService, bool $allProducts): int
    {
        $this->line('1. Sincronizando catálogo maestro de laboratorios...');
        $syncResult = $syncService->syncLaboratoriesFromMaster();
        $this->line("   • Laboratorios creados en tenant: <fg=green>{$syncResult['imported']}</>");
        $this->line("   • Total en Master: <fg=cyan>{$syncResult['total_master']}</>");

        $this->newLine();
        $this->line('2. Reasignando productos a su laboratorio correspondiente...');
        $remapResult = $syncService->remapTenantProductsLaboratories(!$allProducts);

        $this->line("   • Productos evaluados: <fg=yellow>{$remapResult['total_evaluated']}</>");
        $this->line("   • Productos reasignados: <fg=green>{$remapResult['remapped']}</>");
        $this->line("   • Productos restantes en Generales: <fg=cyan>{$remapResult['remaining_generales']}</>");

        $this->newLine();
        $this->info('Proceso finalizado exitosamente.');

        return self::SUCCESS;
    }
}
