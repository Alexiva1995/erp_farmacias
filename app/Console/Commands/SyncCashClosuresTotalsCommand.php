<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CashClosing;
use App\Models\DailyCashClosure;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCashClosuresTotalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-cash-closures-totals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula y sincroniza los totales de cierres de caja y cierres diarios para cuadrar exactamente con las órdenes completadas.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando sincronización de totales entre Pedidos, Cierres de Caja y Cierres Diarios...');

        DB::beginTransaction();
        try {
            // 1. Recalcular todas las cajas individuales
            $closings = CashClosing::all();
            $updatedClosings = 0;

            foreach ($closings as $closing) {
                $closing->recalculateTotals();
                $updatedClosings++;
            }

            // 2. Recalcular todos los cierres diarios
            $dailyClosures = DailyCashClosure::with('cashClosings')->get();
            $updatedDaily = 0;

            foreach ($dailyClosures as $daily) {
                $attachedBoxes = $daily->cashClosings;
                if ($attachedBoxes->isEmpty()) {
                    continue;
                }

                $daily->update([
                    'total_sales'    => round((float) $attachedBoxes->sum('total_sales'), 2),
                    'total_usd'      => round((float) $attachedBoxes->sum('total_usd'), 2),
                    'total_cop'      => round((float) $attachedBoxes->sum('total_cop'), 2),
                    'total_bs'       => round((float) $attachedBoxes->sum('total_bs'), 2),
                    'bs_card'        => round((float) ($attachedBoxes->sum('bs_card_debito') + $attachedBoxes->sum('bs_card_credit')), 2),
                    'bs_cash'        => round((float) $attachedBoxes->sum('bs_cash'), 2),
                    'bs_card_debito' => round((float) $attachedBoxes->sum('bs_card_debito'), 2),
                    'bs_card_credit' => round((float) $attachedBoxes->sum('bs_card_credit'), 2),
                    'bs_transfer'    => round((float) $attachedBoxes->sum('bs_transfer'), 2),
                    'bs_mobile'      => round((float) $attachedBoxes->sum('bs_mobile'), 2),
                    'usd_cash'       => round((float) $attachedBoxes->sum('usd_cash'), 2),
                    'usd_transfer'   => round((float) $attachedBoxes->sum('usd_transfer'), 2),
                    'usd_paypal'     => round((float) $attachedBoxes->sum('usd_paypal'), 2),
                    'usd_binance'    => round((float) $attachedBoxes->sum('usd_binance'), 2),
                    'cop_cash'       => round((float) $attachedBoxes->sum('cop_cash'), 2),
                    'cop_transfer'   => round((float) $attachedBoxes->sum('cop_transfer'), 2),
                    'cop_spare'      => round((float) $attachedBoxes->sum('cop_spare'), 2),
                    'usd_delivered'  => round((float) $attachedBoxes->sum('usd_delivered'), 2),
                    'cop_delivered'  => round((float) $attachedBoxes->sum('cop_delivered'), 2),
                    'bs_delivered'   => round((float) $attachedBoxes->sum('bs_delivered'), 2),
                    'total_credits'  => round((float) $attachedBoxes->sum('usd_credit'), 2),
                ]);

                $updatedDaily++;
            }

            DB::commit();

            // 3. Mostrar resumen de verificación
            $totalOrdersCompleted = (float) Order::where('status', Order::COMPLETED)->sum('total_amount_usd');
            $totalCashClosings    = (float) CashClosing::sum('total_sales');
            $totalDailyClosures   = (float) DailyCashClosure::sum('total_sales');

            $this->info("✓ Se recalcularon {$updatedClosings} cierres de caja.");
            $this->info("✓ Se actualizaron {$updatedDaily} cierres diarios consolidados.");

            $this->table(
                ['Métrica / Origen', 'Total Ventas (USD)'],
                [
                    ['Pedidos Completados (orders.total_amount_usd)', '$' . number_format($totalOrdersCompleted, 2)],
                    ['Cierres de Caja (cash_closing.total_sales)',    '$' . number_format($totalCashClosings, 2)],
                    ['Cierres Diarios (daily_closures.total_sales)',   '$' . number_format($totalDailyClosures, 2)],
                ]
            );

            if (abs($totalOrdersCompleted - $totalCashClosings) < 0.01 && abs($totalOrdersCompleted - $totalDailyClosures) < 0.01) {
                $this->info('🎉 ¡Todos los totales están 100% cuadrados y sincronizados!');
            } else {
                $this->warn('Atención: Aún existen diferencias que podrían deberse a cajas sin asociar a cierres diarios.');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error durante la sincronización: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
