<?php

namespace App\Console\Commands;

use App\Contracts\Suppliers\DromegaScraperServiceInterface;
use Illuminate\Console\Command;

class SyncDromegaInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dromega:sync-invoices {--cookie= : Cookie de sesión autenticada de WordPress}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza las facturas y montos directamente del estado de cuenta de Droguería Mega';

    /**
     * Execute the console command.
     */
    public function handle(DromegaScraperServiceInterface $scraperService): int
    {
        $this->info('Iniciando sincronización con el portal de Droguería Mega...');

        try {
            $cookie = $this->option('cookie');
            $result = $scraperService->syncInvoices($cookie);

            $this->info('Proceso completado exitosamente.');
            $this->line("Total extraídas del portal : {$result['total_extracted']}");
            $this->line("Facturas creadas en ERP    : {$result['created']}");
            $this->line("Facturas actualizadas      : {$result['updated']}");
            $this->line("Facturas omitidas          : {$result['skipped']}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error durante la sincronización: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
