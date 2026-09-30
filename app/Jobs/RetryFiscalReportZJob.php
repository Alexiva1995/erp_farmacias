<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Fiscal\FiscalActionService;
use App\Services\Fiscal\FiscalZReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RetryFiscalReportZJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $targetDate,
        public int $retryCount = 1
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        FiscalActionService $actionService,
        FiscalZReportService $zReportService
    ): void {
        // Verificar si el reporte Z de la fecha ya fue cerrado exitosamente
        $currentReport = $zReportService->getReportByDate($this->targetDate);
        if ($currentReport && $currentReport->status === 'closed') {
            Log::info("[FiscalReportZ] Reintento #{$this->retryCount} omitido: El Reporte Z de la fecha {$this->targetDate} ya se encuentra cerrado.");
            return;
        }

        // Reencolar comando de impresión física de Reporte Z
        $command = $actionService->enqueueCommand('REPORT_Z', [
            'target_date' => $this->targetDate,
            'source'      => "auto_retry_timeout_attempt_{$this->retryCount}",
            'retry_count' => $this->retryCount,
        ]);

        Log::info("[FiscalReportZ] Reintento #{$this->retryCount} encolado para emisión de Reporte Z (Comando #{$command->id}) para la fecha {$this->targetDate}.");
    }
}
