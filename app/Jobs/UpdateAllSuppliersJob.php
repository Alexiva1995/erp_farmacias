<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\SupplierConnectionStatus;
use App\Services\Suppliers\SupplierConnectionService;
use App\Services\Suppliers\SupplierQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateAllSuppliersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * Recibe el usuario que disparó el proceso (o un ID de sistema/admin).
     */
    public function __construct(
        public int $userId
    ) {
    }

    /**
     * Execute the job.
     */
    /**
     * Execute the job.
     */
    public function handle(SupplierConnectionService $connectionService, SupplierQueryService $queryService): void
    {
        $suppliers = Supplier::whereHas('connections')->cursor();

        foreach ($suppliers as $supplier) {

            $status = SupplierConnectionStatus::create([
                "supplier_id" => $supplier->id,
                "user_id" => $this->userId,
                "status" => "processing",
                "message" => "Iniciando actualización masiva..."
            ]);

            try {
                $supplierConnection = $supplier->connections->first();

                if (!$supplierConnection) {
                    $status->update([
                        "status" => "failed",
                        "message" => "No se encontró configuración de conexión válida.",
                    ]);
                    continue;
                }

                $results = $connectionService->fetchData($supplierConnection);

                if (isset($results['invoices']) && is_array($results['invoices'])) {
                    foreach ($results['invoices'] as &$invoice) {
                        $invoice['status'] = 'pending';
                    }
                    unset($invoice);
                }

                $success = $queryService->storeSupplierConnectionData($supplier, $results);

                if (!$success) {
                    throw new \Exception("Error al guardar los datos en la base de datos.");
                }
                if (!in_array($supplier->id, [2])) {
                    $queryService->addDiscountsToProducts($supplier);
                }

                $supplierConnection->update(["last_connection" => now()->today()]);

                $status->update([
                    "status" => "completed",
                    "message" => "Actualización masiva completada correctamente",
                    "count_product" => count($results["products"] ?? []),
                    "count_invoice" => count($results["invoices"] ?? []),
                ]);

            } catch (\Throwable $e) {

                Log::error("Fallo actualización masiva para proveedor {$supplier->id}: " . $e->getMessage());

                $status->update([
                    "status" => "failed",
                    "message" => "Error en actualización masiva: " . $e->getMessage(),
                ]);

            }
        }
    }
}
