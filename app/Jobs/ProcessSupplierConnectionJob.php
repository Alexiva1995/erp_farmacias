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

class ProcessSupplierConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Supplier $supplier, public int $userId) {}

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

        try {
            $results = $connectionService->fetchData($supplierConnection);
            $queryService->storeSupplierConnectionData($this->supplier, $results);
            $queryService->addDiscountsToProducts($this->supplier);

            $supplierConnection->update(["last_connection" => now()->today()]);

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
