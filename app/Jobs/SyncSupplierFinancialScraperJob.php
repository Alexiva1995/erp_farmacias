<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Suppliers\SupplierFinancialSyncServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSupplierFinancialScraperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos del Job.
     */
    public int $tries = 2;

    /**
     * Tiempo de espera en segundos antes de timeout.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $supplierId,
        public ?string $onlyInvoice = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(SupplierFinancialSyncServiceInterface $financialSyncService): void
    {
        Log::info("[SyncSupplierFinancialScraperJob] Ejecutando sincronización de finanzas para Proveedor ID: {$this->supplierId}");

        $result = $financialSyncService->syncSupplierFinancials($this->supplierId, $this->onlyInvoice);

        if (!empty($result['success'])) {
            Log::info("[SyncSupplierFinancialScraperJob] ✅ Finalizado con éxito para Proveedor ID: {$this->supplierId}", $result);
        } else {
            Log::warning("[SyncSupplierFinancialScraperJob] ⚠️ Finalizado con advertencias para Proveedor ID: {$this->supplierId}", $result);
        }
    }
}
