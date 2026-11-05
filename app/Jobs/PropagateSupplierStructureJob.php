<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Tenancy\SupplierSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PropagateSupplierStructureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * @param int $supplierId ID del proveedor en el Master
     * @param string $type Tipo de conexión ('file', 'ftp', 'api', etc.)
     * @param array<string, mixed> $payload Datos de estructura técnica a propagar
     */
    public function __construct(
        protected int $supplierId,
        protected string $type,
        protected array $payload
    ) {}

    public function handle(SupplierSyncService $syncService): void
    {
        Log::info("Iniciando propagación de estructura técnica para proveedor #{$this->supplierId} ({$this->type}) hacia todos los tenants.");

        $results = $syncService->propagateStructureToTenants($this->supplierId, $this->type, $this->payload);

        $successful = count(array_filter($results));
        $total = count($results);

        Log::info("Propagación de estructura completada: {$successful}/{$total} tenants actualizados.");
    }
}
