<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Bi\InventorySnapshotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class GenerateMonthlyInventorySnapshotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-inventory-snapshot {--date= : Fecha de corte YYYY-MM-DD} {--name= : Nombre personalizado} {--days=30 : Periodo de ventas en días}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera una Foto Finish de Inventario (Snapshot de existencias, ventas y desempeño ABC)';

    /**
     * Execute the console command.
     */
    public function handle(InventorySnapshotService $service): int
    {
        $dateOption = $this->option('date');
        $nameOption = $this->option('name');
        $daysOption = (int) $this->option('days');

        // Si no se indica fecha y se ejecuta el día 1, la fecha de corte es el último día del mes anterior
        $isAutomatic = empty($dateOption);
        $cutoffDate = $dateOption ? Carbon::parse($dateOption) : now()->subDay()->endOfDay();

        $snapshotName = $nameOption ?: ('Cierre Mensual ' . $cutoffDate->locale('es')->translatedFormat('F Y'));

        $this->info("Iniciando generación de Foto Finish al corte: {$cutoffDate->toDateString()}...");

        try {
            $snapshot = $service->generateSnapshot(
                cutoffDateStr: $cutoffDate->toDateString(),
                periodDays: $daysOption > 0 ? $daysOption : 30,
                name: $snapshotName,
                userId: null,
                isAutomatic: $isAutomatic
            );

            $this->info("Foto Finish #{$snapshot->id} generada exitosamente:");
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Nombre', $snapshot->name],
                    ['Fecha Corte', $snapshot->cutoff_date->toDateString()],
                    ['Total Productos', $snapshot->total_products],
                    ['Unidades en Stock', number_format($snapshot->total_inventory_units, 2)],
                    ['Valor Inventario ($)', '$' . number_format($snapshot->total_inventory_value, 2)],
                    ['Ventas 30d ($)', '$' . number_format($snapshot->total_sales_value, 2)],
                    ['Productos en Sobrestock', $snapshot->overstock_products_count],
                    ['Valor Sobrestock ($)', '$' . number_format($snapshot->overstock_inventory_value, 2)],
                ]
            );

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Error generando Foto Finish: ' . $e->getMessage());
            \Log::error('[GenerateInventorySnapshotCommand] Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return Command::FAILURE;
        }
    }
}
