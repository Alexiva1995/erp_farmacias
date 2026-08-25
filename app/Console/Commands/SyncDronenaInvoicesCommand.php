<?php

namespace App\Console\Commands;

use App\Contracts\Suppliers\DronenaScraperServiceInterface;
use Illuminate\Console\Command;

class SyncDronenaInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dronena:sync-invoices 
                            {--user= : Usuario de acceso a Dronena} 
                            {--pass= : Contraseña de acceso a Dronena} 
                            {--supplier= : ID del proveedor en BD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extrae y sincroniza las fechas de vencimiento, fechas de pago e indexación desde Dronena';

    /**
     * Execute the console command.
     */
    public function handle(DronenaScraperServiceInterface $scraperService): int
    {
        $user = $this->option('user');
        $pass = $this->option('pass');
        $supplierId = $this->option('supplier') ? (int) $this->option('supplier') : null;

        $this->info('Iniciando sincronización de facturas desde Dronena...');

        try {
            $result = $scraperService->syncInvoices($user, $pass, $supplierId);

            $this->info("Proceso finalizado con éxito:");
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Total Extraídas', $result['total_extracted']],
                    ['Actualizadas en ERP', $result['updated']],
                    ['No encontradas / Omitidas', $result['skipped']],
                    ['ID Proveedor', $result['supplier_id'] ?? 'Detectado automáticamente'],
                ]
            );

            if (!empty($result['details'])) {
                $this->newLine();
                $this->info('Detalle de facturas procesadas:');
                $rows = array_map(function ($item) {
                    return [
                        $item['invoice_number'],
                        $item['action'],
                        $item['exp_date'] ?? 'N/A',
                        $item['payment_date'] ?? 'N/A',
                        $item['is_indexed'] ? 'SÍ (FA$)' : 'NO (FA)',
                    ];
                }, $result['details']);

                $this->table(
                    ['N° Factura', 'Acción', 'Vencimiento', 'Fecha Pago', 'Indexada'],
                    $rows
                );
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error durante la sincronización: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
