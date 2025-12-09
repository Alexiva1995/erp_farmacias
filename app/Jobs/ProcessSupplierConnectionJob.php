<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\SupplierConnectionStatus;
use App\Services\Suppliers\SupplierConnectionService;
use App\Services\Suppliers\SupplierQueryService;
use App\Exports\SupplierImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessSupplierConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Supplier $supplier,
        public int $userId,
        public ?string $filePath = null,
        public array $columnMap = [],
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(SupplierConnectionService $connectionService, SupplierQueryService $queryService): void
    {
        $status = SupplierConnectionStatus::create([
            "supplier_id" => $this->supplier->id,
            "user_id" => $this->userId,
            "status" => "processing",
        ]);

        $supplierConnection = $this->supplier->connections->first();
        if (is_null($supplierConnection) && !$this->filePath) {
            $status->update([
                "status" => "failed",
                "message" => "Este proveedor no posee una conexión registrada",
            ]);
            return;
        }

        $structure = $supplierConnection->structure ?? null;

        try {
            $results = [];

            if ($this->filePath) {
                if (!$supplierConnection || $structure !== $this->columnMap) {
                    $data = [
                        'supplier_id' => $this->supplier->id,
                        'type' => 'file',
                        'host' => "{$this->supplier->name} (Excel)",
                        'pasv' => 0,
                        'has_header' => 0,
                        'last_connection' => now()->format('Y-m-d'),
                        'structure' => $this->columnMap
                    ];
                    $queryService->storeConnection($data);
                }

                if (!Storage::disk('local')->exists($this->filePath)) {
                    throw new \Exception("Archivo subido no encontrado: {$this->filePath}");
                }

                $absolutePath = Storage::disk('local')->path($this->filePath);

                $import = new SupplierImport(
                    supplierId: (int) $this->supplier->id,
                    startRow: (int) ($this->columnMap["start_row"] ?? 1),
                    codSupplierCol: $this->columnMap["cod_supplier"] ?? null,
                    nameCol: $this->columnMap["name"],
                    barcodeCol: $this->columnMap["barcode_match"] ?? null,
                    qtyCol: $this->columnMap["quantity"] ?? null,
                    costBsCol: $this->columnMap["unit_cost"] ?? null,
                    costUsdCol: $this->columnMap["unit_cost_usd"] ?? null,
                    activeIngredientCol: $this->columnMap["active_ingredient"] ?? null,
                    expirationCol: $this->columnMap["expiration"] ?? null,
                    currencyCol: $this->columnMap["currency"] ?? null,
                );

                Excel::import($import, $absolutePath);

                $products = $import->getRows();
                $results = [
                    "products" => $products->isNotEmpty() ? $products->toArray() : [],
                    "invoices" => []
                ];

                Storage::disk('local')->delete($this->filePath);
            } else {
                $results = $connectionService->fetchData($this->supplier->connections->first());
            }

            if (isset($results['invoices']) && is_array($results['invoices'])) {
                foreach ($results['invoices'] as &$invoice) {
                    $invoice['status'] = 'pending';
                }
                unset($invoice);
            }

            $queryService->storeSupplierConnectionData($this->supplier, $results);

            if (!in_array($this->supplier->id, [2]))
                $queryService->addDiscountsToProducts($this->supplier);

            if (!$this->filePath) {
                $supplierConnection->update(["last_connection" => now()->today()]);
            }

            $status->update([
                "status" => "completed",
                "message" => "Conexión procesada correctamente",
                "count_product" => count($results["products"]),
                "count_invoice" => count($results["invoices"]),
            ]);

        } catch (\Throwable $e) {
            // ... (Manejo de errores existente) ...
            \Log::error('Supplier import failed', [
                'supplier_id' => $this->supplier->id,
                'file' => $this->filePath ?? 'none',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $status->update([
                "status" => "failed",
                "message" => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
