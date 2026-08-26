<?php

namespace App\Console\Commands;

use App\Contracts\Suppliers\DrocercaScraperServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDrocercaInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drocerca:sync-invoices 
                            {--user= : Usuario del portal Drocerca}
                            {--password= : Contraseña del portal Drocerca}
                            {--supplier= : ID del proveedor Drocerca en la base de datos}
                            {--invoice= : Sincronizar únicamente una factura específica}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza y descarga automáticamente las facturas, vencimientos y totales fiscales desde el portal de Drocerca';

    /**
     * Execute the console command.
     */
    public function handle(DrocercaScraperServiceInterface $scraperService): int
    {
        $this->info('Iniciando sincronización automática con el portal de Drocerca...');

        $user = $this->option('user');
        $pass = $this->option('password');
        $supplierId = $this->option('supplier') ? (int) $this->option('supplier') : null;
        $onlyInvoice = $this->option('invoice');

        try {
            $result = $scraperService->syncInvoices($user, $pass, $supplierId, $onlyInvoice);

            $this->info("Proceso completado.");
            $this->line("Total extraídas del portal : " . $result['total_extracted']);
            $this->line("Facturas creadas en ERP    : " . $result['created']);
            $this->line("Facturas actualizadas      : " . $result['updated']);
            $this->line("Facturas omitidas          : " . $result['skipped']);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error ejecutando la sincronización de Drocerca: ' . $e->getMessage());
            Log::error('[DrocercaSyncCommand] Error: ' . $e->getMessage(), ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
