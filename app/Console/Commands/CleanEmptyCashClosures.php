<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CashClosing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanEmptyCashClosures extends Command
{
    /**
     * El nombre y firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'app:clean-empty-cash-closures';

    /**
     * La descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Elimina de la base de datos todas las cajas registradas con ventas e ingresos en 0 y sin órdenes activas.';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(): int
    {
        $this->info('Iniciando limpieza de cajas vacías en 0...');

        try {
            DB::beginTransaction();

            // Buscar IDs de cajas vacías (ventas y montos en 0 sin órdenes asociadas)
            $emptyClosuresQuery = CashClosing::where(function ($q) {
                $q->where('total_sales', 0)
                  ->where('total_cop', 0)
                  ->where('total_usd', 0)
                  ->where('total_bs', 0)
                  ->where('cop_cash', 0)
                  ->where('usd_cash', 0)
                  ->where('bs_cash', 0);
            })->whereDoesntHave('orders');

            $count = $emptyClosuresQuery->count();

            if ($count === 0) {
                $this->info('No se encontraron cajas vacías para eliminar.');
                DB::rollBack();
                return Command::SUCCESS;
            }

            // Desvincular cualquier relación residual
            $emptyClosureIds = $emptyClosuresQuery->pluck('id');
            DB::table('orders')->whereIn('cash_closing_id', $emptyClosureIds)->update(['cash_closing_id' => null]);

            // Eliminar registros
            $deletedCount = CashClosing::whereIn('id', $emptyClosureIds)->delete();

            DB::commit();

            $this->info("🟢 Se eliminaron exitosamente {$deletedCount} cajas vacías registradas en 0.");
            Log::info("CleanEmptyCashClosures: Se eliminaron {$deletedCount} cajas vacías registradas en 0.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error al limpiar las cajas vacías: {$e->getMessage()}");
            Log::error("CleanEmptyCashClosures Error: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
