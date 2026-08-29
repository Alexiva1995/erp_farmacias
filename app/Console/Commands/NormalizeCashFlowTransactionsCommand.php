<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CashClosing;
use App\Models\Transaction;
use App\Services\CashClosure\CashClosureActionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizeCashFlowTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:normalize-cash-flow {--dry-run : Muestra los cambios que se realizarían sin modificar la base de datos} {--box= : Normalizar únicamente una caja específica por su ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia transacciones duplicadas de flujo de caja y recalcula las entradas de cada cierre de caja con sus fechas reales y montos exactos.';

    /**
     * Execute the console command.
     */
    public function handle(CashClosureActionService $cashClosureService): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $boxId = $this->option('box');

        $this->info('====================================================');
        $this->info('  NORMALIZADOR DE TRANSACCIONES DE FLUJO DE CAJA   ');
        $this->info('====================================================');

        if ($isDryRun) {
            $this->warn('MODO DRY-RUN ACTIVADO: No se aplicarán cambios a la base de datos.');
        }

        // Consultar cajas cerradas o con ventas
        $query = CashClosing::query();
        if ($boxId) {
            $query->where('id', (int) $boxId);
        } else {
            $query->where(function ($q) {
                $q->where('status', CashClosing::CLOSED)
                  ->orWhere('total_sales', '>', 0);
            });
        }

        $cashClosings = $query->orderBy('id', 'asc')->get();

        if ($cashClosings->isEmpty()) {
            $this->info('No se encontraron cajas para procesar.');
            return Command::SUCCESS;
        }

        $this->line("Se encontraron <fg=cyan>{$cashClosings->count()}</> cajas para procesar.");

        $existingClosureTxCount = Transaction::where('description', 'like', 'Cierre de caja #%')->count();
        $this->line("Total de transacciones de cierres de caja actuales en el sistema: <fg=yellow>{$existingClosureTxCount}</>");

        if ($isDryRun) {
            foreach ($cashClosings as $box) {
                $date = $box->closing_date ? Carbon::parse($box->closing_date)->toDateString() : 'N/A';
                $this->line("- Caja #{$box->id} | Vendedor ID: {$box->seller_id} | Fecha Cierre: {$date} | Ventas USD: {$box->total_sales}");
            }
            $this->info('✓ Simulación completada con éxito.');
            return Command::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $processed = 0;

            foreach ($cashClosings as $box) {
                // Sincronizar fecha real de la caja si no la tiene definida
                if (empty($box->closing_date)) {
                    $latestOrder = $box->orders()->where('status', 'Completed')->orderByDesc('created_at')->first();
                    if ($latestOrder && $latestOrder->created_at) {
                        $box->closing_date = Carbon::parse($latestOrder->created_at)->toDateTimeString();
                        $box->save();
                    } elseif (!empty($box->created_at)) {
                        $box->closing_date = Carbon::parse($box->created_at)->toDateTimeString();
                        $box->save();
                    }
                }

                // Regenerar transacciones de forma idempotente con fechas reales
                $cashClosureService->generateClosingTransactions($box);
                $processed++;
            }

            DB::commit();

            $newClosureTxCount = Transaction::where('description', 'like', 'Cierre de caja #%')->count();
            $this->info('====================================================');
            $this->info("✓ Se procesaron y normalizaron {$processed} cajas exitosamente.");
            $this->info("✓ Transacciones anteriores: {$existingClosureTxCount} -> Nuevas transacciones normalizadas: {$newClosureTxCount}");
            $this->info('====================================================');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al normalizar transacciones de flujo de caja: ' . $e->getMessage());
            $this->error('✗ Error durante la normalización: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
