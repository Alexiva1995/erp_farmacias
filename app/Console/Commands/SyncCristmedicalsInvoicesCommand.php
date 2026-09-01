<?php

namespace App\Console\Commands;

use App\Contracts\Suppliers\CristmedicalsScraperServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCristmedicalsInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cristmedicals:sync-invoices 
                            {--user= : Usuario del portal web de Cristmedicals}
                            {--password= : Contraseña del portal web de Cristmedicals}
                            {--supplier= : ID del proveedor Cristmedicals en la base de datos}
                            {--invoice= : Sincronizar únicamente una factura específica}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza automáticamente las facturas, vencimientos, saldos con descuento y montos en Bs desde el portal web de Cristmedicals';

    /**
     * Execute the console command.
     */
    public function handle(CristmedicalsScraperServiceInterface $scraperService): int
    {
        $this->info('Iniciando sincronización con el portal web de Cristmedicals...');

        $user = $this->option('user');
        $pass = $this->option('password');
        $supplierId = $this->option('supplier') ? (int) $this->option('supplier') : null;
        $onlyInvoice = $this->option('invoice');

        try {
            $result = $scraperService->syncInvoices($user, $pass, $supplierId, $onlyInvoice);

            $this->info("Proceso completado exitosamente.");
            $this->line("Total extraídas del portal : " . ($result['total_extracted'] ?? 0));
            $this->line("Facturas creadas en ERP    : " . ($result['created'] ?? 0));
            $this->line("Facturas actualizadas      : " . ($result['updated'] ?? 0));
            $this->line("Facturas omitidas          : " . ($result['skipped'] ?? 0));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error ejecutando la sincronización de Cristmedicals: ' . $e->getMessage());
            Log::error('[CristmedicalsSyncCommand] Error: ' . $e->getMessage(), ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}