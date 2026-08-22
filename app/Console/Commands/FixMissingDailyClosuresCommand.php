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
    protected $signature = 'app:fix-missing-daily-closures {--date= : Fecha específica en formato YYYY-MM-DD} {--sync-dates : Reajusta las fechas de los cierres diarios creados para que coincidan con la fecha real de sus cajas} {--rebuild-all : Desvincula y recalcula todas las cajas según su fecha real de órdenes y regenera los cierres diarios}';

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
        $syncDates = $this->option('sync-dates');
        $rebuildAll = $this->option('rebuild-all');
        $cashClosureService = app(CashClosureActionService::class);

        // Si se pasa la bandera --rebuild-all, recalcular y reconstruir todo limpiamente
        if ($rebuildAll) {
            $this->info('Iniciando reconstrucción completa de cajas y cierres diarios...');
            DB::beginTransaction();
            try {
                // 1. Desvincular todas las cajas y vaciar cierres diarios
                DB::table('cash_closing')->update(['daily_closure_id' => null]);
                DB::table('daily_closures')->delete();

                // 2. Corregir fechas reales de cada caja según sus órdenes
                $allBoxes = CashClosing::all();
                foreach ($allBoxes as $box) {
                    $latestOrder = $box->orders()->where('status', 'Completed')->orderByDesc('created_at')->first();
                    if ($latestOrder && $latestOrder->created_at) {
                        $realDate = Carbon::parse($latestOrder->created_at)->toDateString();
                        $box->closing_date = $realDate;
                        $box->save();
                    }
                }

                // 3. Limpiar valores corruptos conocidos
                DB::table('cash_closing')->where('id', 2)->update([
                    'cop_delivered' => 0.00,
                    'cop_cash_payment_credit' => 0.00,
                    'cop_conversion_payment_credit' => 0.00,
                ]);

                // 4. Redondear entrega COP en caja 5 si corresponde a vuelto de 47.000
                $box5 = CashClosing::find(5);
                if ($box5 && $box5->total_cop == 47000) {
                    $box5->cop_cash = 47000.00;
                    $box5->cop_delivered = 47000.00;
                    $box5->save();
                }

                DB::commit();
                $this->info('✓ Cajas ajustadas con sus fechas reales y valores saneados.');
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error('Error durante la preparación: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        // Si se pasa la bandera --sync-dates, corregir las fechas de los daily_closures ya creados
        if ($syncDates) {
            $this->info('Sincronizando fechas de cierres diarios existentes con sus cajas asociadas...');
            $closures = DailyCashClosure::with('cashClosings')->get();
            $synced = 0;

            foreach ($closures as $dc) {
                $firstBox = $dc->cashClosings->first();
                if ($firstBox && $firstBox->closing_date) {
                    $boxDate = Carbon::parse($firstBox->closing_date)->format('Y-m-d') . ' 23:59:59';
                    DB::table('daily_closures')
                        ->where('id', $dc->id)
                        ->update([
                            'created_at' => $boxDate,
                            'updated_at' => $boxDate,
                        ]);
                    $synced++;
                }
            }

            $this->info("✓ Se corrigieron las fechas de {$synced} cierre(s) diario(s).");
            return Command::SUCCESS;
        }

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

                $targetTimestamp = Carbon::parse($day . ' 23:59:59')->toDateTimeString();

                $dailyClosure = new DailyCashClosure([
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
                ]);

                // Desactivar timestamps automáticos de Eloquent para preservar la fecha retroactiva exacta
                $dailyClosure->timestamps = false;
                $dailyClosure->created_at = $targetTimestamp;
                $dailyClosure->updated_at = $targetTimestamp;
                $dailyClosure->save();

                foreach ($cashClosings as $cashClosing) {
                    $cashClosing->update([
                        'status'           => CashClosing::CLOSED,
                        'daily_closure_id' => $dailyClosure->id,
                        'closing_date'     => $cashClosing->closing_date ?? $targetTimestamp,
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
