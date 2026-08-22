<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CashClosing;
use App\Models\DailyCashClosure;
use App\Services\CashClosure\CashClosureActionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixMissingDailyClosuresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-missing-daily-closures {--date= : Fecha específica en formato YYYY-MM-DD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera de forma retroactiva todos los cierres diarios consolidados pendientes o no procesados por el cron.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificDate = $this->option('date');
        $cashClosureService = app(CashClosureActionService::class);

        $this->info('Iniciando verificación de cierres diarios pendientes...');

        // Consultar fechas únicas que tengan cajas con ventas y sin cierre diario consolidado
        $query = CashClosing::where('total_sales', '>', 0.0)
            ->whereNull('daily_closure_id');

        if ($specificDate) {
            $query->whereDate('closing_date', $specificDate);
        }

        $dates = $query->select(DB::raw('DATE(closing_date) as closing_day'))
            ->groupBy('closing_day')
            ->orderBy('closing_day', 'asc')
            ->pluck('closing_day');

        if ($dates->isEmpty()) {
            $this->info('✓ No se encontraron cajas pendientes de cierre consolidado.');
            return Command::SUCCESS;
        }

        $this->info("Se encontraron {$dates->count()} día(s) con cierres pendientes.");

        $processedCount = 0;

        foreach ($dates as $day) {
            $this->line("Procesando fecha: {$day}...");

            $cashClosings = CashClosing::whereDate('closing_date', $day)
                ->where('total_sales', '>', 0.0)
                ->whereNull('daily_closure_id')
                ->get();

            if ($cashClosings->isEmpty()) {
                continue;
            }

            DB::beginTransaction();
            try {
                $dailyCashClosureInstance = new DailyCashClosure();
                $totalCopPaymentInUsd = $dailyCashClosureInstance->getTotalCopPaymentInUsd($cashClosings);
                $totalBsPaymentInUsd = $dailyCashClosureInstance->getTotalBsPaymentInUsd($cashClosings);
                $totalCopDeliveryInUsd = $dailyCashClosureInstance->getTotalCopDeliveryInUsd($cashClosings);
                $totalBsDeliveryInUsd = $dailyCashClosureInstance->getTotalBsDeliveryInUsd($cashClosings);

                $dailyClosure = DailyCashClosure::create([
                    'total_sales'          => $cashClosings->sum('total_sales'),
                    'total_usd'            => $cashClosings->sum('total_usd'),
                    'total_cop'            => $cashClosings->sum('total_cop'),
                    'total_bs'             => $cashClosings->sum('total_bs'),
                    'bs_card'              => $cashClosings->sum('bs_card_debito') + $cashClosings->sum('bs_card_credit'),
                    'bs_cash'              => $cashClosings->sum('bs_cash'),
                    'bs_card_debito'       => $cashClosings->sum('bs_card_debito'),
                    'bs_card_credit'       => $cashClosings->sum('bs_card_credit'),
                    'bs_transfer'          => $cashClosings->sum('bs_transfer'),
                    'bs_mobile'            => $cashClosings->sum('bs_mobile'),
                    'usd_cash'             => $cashClosings->sum('usd_cash'),
                    'usd_transfer'         => $cashClosings->sum('usd_transfer'),
                    'usd_paypal'           => $cashClosings->sum('usd_paypal'),
                    'usd_binance'          => $cashClosings->sum('usd_binance'),
                    'cop_cash'             => $cashClosings->sum('cop_cash'),
                    'cop_transfer'         => $cashClosings->sum('cop_transfer'),
                    'cop_spare'            => $cashClosings->sum('cop_spare'),
                    'usd_delivered'        => $cashClosings->sum('usd_delivered'),
                    'cop_delivered'        => $cashClosings->sum('cop_delivered'),
                    'bs_delivered'         => $cashClosings->sum('bs_delivered'),
                    'total_credits'        => $cashClosings->sum('usd_credit'),
                    'total_payment_credit' => $cashClosings->sum('usd_transfer_payment_credit') + $cashClosings->sum('usd_cash_payment_credit') + $cashClosings->sum('usd_paypal_payment_credit') + $cashClosings->sum('usd_binance_payment_credit') + $totalCopPaymentInUsd + $totalBsPaymentInUsd,
                    'total_delivery'       => $totalCopDeliveryInUsd + $cashClosings->sum('usd_delivered') + $cashClosings->sum('usd_transfer') + $cashClosings->sum('usd_paypal') + $cashClosings->sum('usd_binance') + $totalBsDeliveryInUsd,
                    'created_at'           => Carbon::parse($day . ' 23:59:59'),
                ]);

                foreach ($cashClosings as $cashClosing) {
                    $cashClosing->update([
                        'status'           => CashClosing::CLOSED,
                        'daily_closure_id' => $dailyClosure->id,
                        'closing_date'     => $cashClosing->closing_date ?? Carbon::parse($day . ' 23:59:59'),
                    ]);

                    $cashClosureService->generateClosingTransactions($cashClosing);
                }

                DB::commit();
                $processedCount++;
                $this->info("✓ Fecha {$day} consolidada exitosamente (ID de Cierre Diario: {$dailyClosure->id}, Cajas: {$cashClosings->count()}).");
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error al consolidar cierre diario para {$day}: {$e->getMessage()}");
                $this->error("✗ Error al procesar fecha {$day}: {$e->getMessage()}");
            }
        }

        $this->info("Proceso completado. Se consolidaron {$processedCount} día(s).");
        return Command::SUCCESS;
    }
}
