<?php

namespace App\Console\Commands;

use App\Services\Fiscal\FiscalZReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyFiscalZReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiscal:generate-z-reports 
                            {--date= : Fecha específica a generar en formato YYYY-MM-DD}
                            {--month= : Mes a procesar (1-12) por defecto mes actual}
                            {--year= : Año a procesar por defecto año actual}
                            {--number=740 : Número objetivo para el día más reciente (o base de corte)}
                            {--start-number= : Alias compatible para --number}
                            {--forward : Numerar en orden ascendente hacia adelante en lugar de hacia atrás}
                            {--force : Forzar sobrescritura de reportes existentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera los reportes Z diarios fiscales numerando hacia atrás desde el número indicado.';

    /**
     * Execute the console command.
     */
    public function handle(FiscalZReportService $zReportService): int
    {
        $specificDate = $this->option('date');
        $force = (bool) $this->option('force');
        $numberOption = $this->option('start-number') ?: $this->option('number');
        $targetNumber = (int) ($numberOption ?: 740);
        $backward = !$this->option('forward');

        if ($specificDate) {
            $this->info("Generando Reporte Z para la fecha: {$specificDate} (N° #{$targetNumber})...");
            $report = $zReportService->generateForDate($specificDate, $targetNumber, true);
            $this->info("✓ Reporte Z #{$report->report_number} procesado para {$report->report_date->format('Y-m-d')} - Total: Bs. " . number_format($report->total_amount, 2, ',', '.'));
            return Command::SUCCESS;
        }

        $monthOption = $this->option('month');
        $yearOption = $this->option('year');

        // Si se llama en el cron a las 00:01 sin opciones específicas, genera el reporte del día anterior
        if ($this->hasOption('month') && $monthOption === null && !$specificDate && !$force && !app()->runningInConsole()) {
            $yesterday = Carbon::yesterday()->format('Y-m-d');
            $this->info("Generando Reporte Z diario automático para {$yesterday}...");
            $report = $zReportService->generateForDate($yesterday, null, true);
            $this->info("✓ Reporte Z #{$report->report_number} generado.");
            return Command::SUCCESS;
        }

        $now = Carbon::now();
        $year = $yearOption ? (int) $yearOption : (int) $now->format('Y');
        $month = $monthOption ? (int) $monthOption : (int) $now->format('m');

        $directionText = $backward ? "hacia atrás terminando en #{$targetNumber}" : "hacia adelante iniciando en #{$targetNumber}";
        $this->info("Generando reportes Z para {$year}-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . " ({$directionText})...");

        $reports = $zReportService->generateForMonth($year, $month, $targetNumber, true, $backward);

        $this->table(
            ['Reporte #', 'Fecha', 'Facturas', 'Exento (Bs.)', 'Base 16% (Bs.)', 'IVA G (Bs.)', 'Base IGTF (Bs.)', 'IGTF (Bs.)', 'Total (Bs.)'],
            $reports->map(function ($r) {
                return [
                    'Z' . str_pad((string)$r->report_number, 6, '0', STR_PAD_LEFT),
                    $r->report_date->format('d/m/Y'),
                    $r->invoices_count,
                    number_format($r->exempt_amount, 2, ',', '.'),
                    number_format($r->base_16_amount, 2, ',', '.'),
                    number_format($r->iva_amount, 2, ',', '.'),
                    number_format($r->igtf_base_amount, 2, ',', '.'),
                    number_format($r->igtf_amount, 2, ',', '.'),
                    number_format($r->total_amount, 2, ',', '.'),
                ];
            })
        );

        $this->info("✓ Se procesaron {$reports->count()} reportes Z exitosamente.");

        return Command::SUCCESS;
    }
}
