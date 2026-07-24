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
     */
    public function __construct(
        public int $userId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(SupplierConnectionService $connectionService, SupplierQueryService $queryService): void
    {
        // 1. Log de inicio general del Job

        $suppliers = Supplier::whereHas('connections')->get();

        foreach ($suppliers as $supplier) {

            // 2. Log de inicio por proveedor (útil si el job se pega, sabes en cuál fue)

            $status = SupplierConnectionStatus::create([
                "supplier_id" => $supplier->id,
                "user_id" => $this->userId,
                "status" => "processing",
                "message" => "Iniciando actualización masiva..."
            ]);

            try {
                $supplierConnection = $supplier->connections->first();

                if (!$supplierConnection) {
                    Log::warning("Proveedor sin configuración de conexión válida", ['supplier_id' => $supplier->id]);

                    $status->update([
                        "status" => "failed",
                        "message" => "No se encontró configuración de conexión válida.",
                    ]);
                    continue;
                }

                $results = $connectionService->fetchData($supplierConnection);

                // Lógica de facturas
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

                // Pre-calculamos conteos para usarlos en DB y en Logs
                $prodCount = count($results["products"] ?? []);
                $invCount = count($results["invoices"] ?? []);

                $status->update([
                    "status" => "completed",
                    "message" => "Actualización masiva completada correctamente",
                    "count_product" => $prodCount,
                    "count_invoice" => $invCount,
                ]);

                // 3. Log de éxito con métricas básicas

            } catch (\Throwable $e) {
                // 4. Log de error con contexto completo (Excepción y ID)
                Log::error("Fallo actualización masiva para proveedor", [
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);

                $status->update([
                    "status" => "failed",
                    "message" => "Error en actualización masiva: " . $e->getMessage(),
                ]);
            }
        }

        // 5. Log de finalización del Job completo
    }
}
