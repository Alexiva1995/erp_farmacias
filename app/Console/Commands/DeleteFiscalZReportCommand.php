<?php

namespace App\Console\Commands;

use App\Models\FiscalZReport;
use App\Services\Fiscal\FiscalZReportService;
use Illuminate\Console\Command;

class DeleteFiscalZReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiscal:delete-z-report 
                            {--number= : Número de reporte Z a eliminar (ej. 740)}
                            {--date= : Fecha del reporte Z a eliminar (ej. 2026-09-18)}
                            {--all : Eliminar todos los reportes Z existentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina uno o varios reportes Z por número, fecha o vaciado total.';

    /**
     * Execute the console command.
     */
    public function handle(FiscalZReportService $zReportService): int
    {
        $number = $this->option('number');
        $date = $this->option('date');
        $all = (bool) $this->option('all');

        if ($all) {
            $count = FiscalZReport::count();
            FiscalZReport::truncate();
            $this->info("✓ Se han eliminado todos los reportes Z ({$count} registros eliminados).");
            return Command::SUCCESS;
        }

        if ($number !== null) {
            $deleted = $zReportService->deleteByNumber((int) $number);
            if ($deleted) {
                $this->info("✓ Reporte Z #{$number} eliminado exitosamente.");
            } else {
                $this->warn("⚠ No se encontró ningún Reporte Z con el número #{$number}.");
            }
            return Command::SUCCESS;
        }

        if ($date !== null) {
            $deleted = $zReportService->deleteByDate($date);
            if ($deleted) {
                $this->info("✓ Reporte Z de la fecha {$date} eliminado exitosamente.");
            } else {
                $this->warn("⚠ No se encontró ningún Reporte Z para la fecha {$date}.");
            }
            return Command::SUCCESS;
        }

        $this->error("Debes especificar al menos una opción: --number=740, --date=YYYY-MM-DD o --all");
        return Command::FAILURE;
    }
}
