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
                        'host' => 'excel',
                        'pasv' => 0,
                        'has_header' => 0,
                        'last_connection' => now()->format('Y-m-d'),
                        'structure' => $this->columnMap
                    ];
                    $queryService->storeConnection($data);
                }

                $import = new SupplierImport(
                    supplierId: (int) $this->supplier->id,
                    startRow: (int) ($this->columnMap["start_row"] ?? 1),
                    codSupplierCol: $this->columnMap["cod_supplier"] ?? "A",
                    nameCol: $this->columnMap["name"] ?? "B",
                    barcodeCol: $this->columnMap["barcode_match"] ?? "C",
                    qtyCol: $this->columnMap["quantity"] ?: null,
                    costBsCol: $this->columnMap["unit_cost"] ?: null,
                    costUsdCol: $this->columnMap["unit_cost_usd"] ?: null,
                    activeIngredientCol: $this->columnMap["active_ingredient"] ?: null,
                    expirationCol: $this->columnMap["expiration"] ?: null,
                    currencyCol: $this->columnMap["currency"] ?: null,
                );

                $absolutePath = Storage::disk("local")->path($this->filePath);

                Excel::import($import, $absolutePath);
                $results = ["products" => $import->getRows()->toArray(), "invoices" => []];

                unlink($absolutePath);
            } else {
                $results = $connectionService->fetchData($supplierConnection);
            }

            $queryService->storeSupplierConnectionData($this->supplier, $results);
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
            \Log::error($e);
            $status->update([
                "status" => "failed",
                "message" => $e->getMessage(),
            ]);
        }
    }
}
