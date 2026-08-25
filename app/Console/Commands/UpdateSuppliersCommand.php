<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierConnectionStatus;
use App\Services\Suppliers\SupplierConnectionService;
use App\Services\Suppliers\SupplierQueryService;
use Illuminate\Console\Command;

class UpdateSuppliersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:update 
                            {--supplier= : ID o nombre del proveedor específico (opcional)} 
                            {--user=1 : ID del usuario que ejecuta la acción}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta la actualización y descarga sincrónica de catálogos y facturas de proveedores desde terminal';

    /**
     * Execute the console command.
     */
    public function handle(
        SupplierConnectionService $connectionService,
        SupplierQueryService $queryService
    ): int {
        $supplierOption = $this->option('supplier');
        $userId = (int) $this->option('user');

        $query = Supplier::where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })
        ->whereHas('connections', function ($q) {
            $q->whereIn('type', ['ftp', 'sftp', 'api', 'http']);
        })
        ->with(['connections' => function ($q) {
            $q->whereIn('type', ['ftp', 'sftp', 'api', 'http']);
        }]);

        if ($supplierOption) {
            if (is_numeric($supplierOption)) {
                $query->where('id', (int) $supplierOption);
            } else {
                $query->where('name', 'LIKE', "%{$supplierOption}%");
            }
        }

        $suppliers = $query->get();

        if ($suppliers->isEmpty()) {
            $this->warn('No se encontraron proveedores activos con conexión remota (FTP/API) configurada.');
            return self::SUCCESS;
        }

        $this->info('========================================================================');
        $this->info(' INICIANDO ACTUALIZACIÓN DE PROVEEDORES DESDE TERMINAL');
        $this->info('========================================================================');
        $this->line("• Proveedores a procesar: <fg=yellow>{$suppliers->count()}</>");
        $this->newLine();

        $successCount = 0;
        $failCount = 0;

        foreach ($suppliers as $index => $supplier) {
            $num = $index + 1;
            $connection = $supplier->connections->first();

            $this->line("------------------------------------------------------------------------");
            $this->line("[{$num}/{$suppliers->count()}] Procesando: <fg=cyan;options=bold>{$supplier->name}</> (ID: {$supplier->id}, Tipo: {$connection->type})");

            $status = SupplierConnectionStatus::create([
                'supplier_id' => $supplier->id,
                'user_id' => $userId,
                'status' => 'processing',
                'message' => 'Iniciando actualización desde terminal...',
            ]);

            $start = microtime(true);

            try {
                $this->line("   📡 Conectando y descargando datos ({$connection->type})...");
                $results = $connectionService->fetchData($connection);

                if (isset($results['invoices']) && is_array($results['invoices'])) {
                    foreach ($results['invoices'] as &$invoice) {
                        $invoice['status'] = 'pending';
                    }
                    unset($invoice);
                }

                $prodCount = count($results['products'] ?? []);
                $invCount = count($results['invoices'] ?? []);

                $this->line("   💾 Guardando en base de datos ({$prodCount} productos, {$invCount} facturas)...");
                $saved = $queryService->storeSupplierConnectionData($supplier, $results);

                if (!$saved) {
                    throw new \Exception('Error al registrar los datos en la base de datos.');
                }

                $isVitalClinic = str_contains(strtolower($supplier->name ?? ''), 'vitalclinic')
                    || in_array($supplier->id, [2, 1009]);

                if (!$isVitalClinic) {
                    $queryService->addDiscountsToProducts($supplier);
                }

                $connection->update(['last_connection' => now()->today()]);

                $duration = round(microtime(true) - $start, 2);

                $status->update([
                    'status' => 'completed',
                    'message' => 'Actualización completada correctamente',
                    'count_product' => $prodCount,
                    'count_invoice' => $invCount,
                ]);

                $this->info("   ✓ Éxito en {$duration}s: {$prodCount} productos y {$invCount} facturas procesadas.");
                $successCount++;

            } catch (\Throwable $e) {
                $duration = round(microtime(true) - $start, 2);

                $status->update([
                    'status' => 'failed',
                    'message' => 'Error en actualización: ' . $e->getMessage(),
                ]);

                $this->error("   ✗ Error en {$duration}s: " . $e->getMessage());
                $failCount++;
            }
        }

        $this->newLine();
        $this->info('========================================================================');
        $this->info("PROCESO FINALIZADO: {$successCount} completados correctamente, {$failCount} con error.");
        $this->info('========================================================================');

        return $failCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
