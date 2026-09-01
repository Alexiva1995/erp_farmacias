<?php

namespace App\Console\Commands;

use App\Contracts\Suppliers\MafartaScraperServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMafartaInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mafarta:sync-invoices 
                            {--user= : Usuario del portal SIC de Cobeca/Mafarta}
                            {--password= : Contraseña del portal SIC de Cobeca/Mafarta}
                            {--supplier= : ID del proveedor Cobeca/Mafarta en la base de datos}
                            {--invoice= : Sincronizar únicamente una factura específica}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza y descarga/regenera automáticamente las facturas, vencimientos, totales y PDFs desde el portal SIC de Cobeca / Mafarta';

    /**
     * Execute the console command.
     */
    public function handle(MafartaScraperServiceInterface $scraperService): int
    {
        $this->info('Iniciando sincronización y regeneración de PDFs con Cobeca / Mafarta...');

        $user = $this->option('user');
        $pass = $this->option('password');
        $supplierId = $this->option('supplier') ? (int) $this->option('supplier') : null;
        $onlyInvoice = $this->option('invoice');

        try {
            $result = $scraperService->syncInvoices($user, $pass, $supplierId, $onlyInvoice);

            $this->info("Proceso completado exitosamente.");
            $this->line("Total extraídas del portal : " . ($result['total_extracted'] ?? 0));
            $this->line("Facturas creadas en ERP    : " . ($result['created'] ?? 0));
            $this->line("Facturas actualizadas/PDFs : " . ($result['updated'] ?? 0));
            $this->line("Facturas omitidas          : " . ($result['skipped'] ?? 0));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error ejecutando la sincronización de Cobeca / Mafarta: ' . $e->getMessage());
            Log::error('[MafartaSyncCommand] Error: ' . $e->getMessage(), ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
