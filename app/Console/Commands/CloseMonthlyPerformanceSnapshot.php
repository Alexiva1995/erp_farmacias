<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmployeePerformance\EmployeePerformanceQueryService;
use Carbon\Carbon;

class CloseMonthlyPerformanceSnapshot extends Command
{
    protected $signature = 'app:close-monthly-performance';
    protected $description = 'Captura un snapshot del desempeño de los empleados del mes que acaba de terminar.';

    public function handle(EmployeePerformanceQueryService $performanceService)
    {
        // El comando corre el dia 1 del mes N, por lo que cerramos el mes N-1
        $targetDate = Carbon::now()->subMonth();
        $month = $targetDate->month;
        $year = $targetDate->year;

        $this->info("Iniciando cierre de desempeño para {$month}/{$year}...");

        try {
            $performanceService->captureSnapshot($month, $year);
            $this->info("Snapshot capturado exitosamente.");
        } catch (\Exception $e) {
            $this->error("Error al capturar snapshot: " . $e->getMessage());
        }
    }
}
