<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Fiscal\FiscalActionService;
use App\Services\Fiscal\FiscalZReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PrintDailyFiscalZReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiscal:print-report-z {--date= : Fecha del corte fiscal en formato YYYY-MM-DD (por defecto hoy)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encola la orden física de impresión de Reporte Z a la impresora fiscal y consolida los datos del día.';

    /**
     * Execute the console command.
     */
    public function handle(
        FiscalActionService $actionService,
        FiscalZReportService $zReportService
    ): int {
        $date = $this->option('date') ?: Carbon::today()->format('Y-m-d');

        $this->info("Iniciando proceso de emisión de Reporte Z para la fecha {$date} a las " . Carbon::now()->format('H:i:s'));

        try {
            // 1. Encolar comando de impresión física hacia el puente de la impresora fiscal
            $command = $actionService->enqueueCommand('REPORT_Z', [
                'target_date' => $date,
                'source'      => 'scheduled_cron_daily_11_59_pm',
            ]);

            $this->info("✓ Comando REPORT_Z encolado exitosamente con ID #{$command->id}.");
            Log::info("[FiscalReportZ] Comando REPORT_Z encolado automáticamente para impresión física (ID #{$command->id}) a las 23:59.");

            // 2. Consolidar o calcular el Reporte Z en base de datos para la fecha
            $report = $zReportService->generateForDate($date, null, true);
            $this->info("✓ Registro fiscal en BD actualizado: Reporte Z #{$report->report_number} - Total: Bs. " . number_format($report->total_amount, 2, ',', '.'));
            Log::info("[FiscalReportZ] Reporte Z #{$report->report_number} consolidado en base de datos con total Bs. {$report->total_amount}.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $errorMsg = "Error al emitir e imprimir el Reporte Z automático: {$e->getMessage()}";
            $this->error($errorMsg);
            Log::error("[FiscalReportZ] {$errorMsg}", [
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
