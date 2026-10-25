<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CashClosing;
use App\Models\DailyCashClosure;
use App\Models\Order;
use App\Services\Resources\ResourceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCopClosingsMismatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-cop-closings-mismatch 
                            {--from=2026-09-22 : Fecha inicial para corrección} 
                            {--closing= : ID específico de cierre a corregir} 
                            {--include-open : Incluir cierres que aún estén abiertos} 
                            {--dry-run : Ejecutar en modo simulación sin guardar en base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige los montos de ventas en órdenes COP con total_amount desfasado y recalcula el efectivo cop_cash y sobrantes de cierre ciego.';

    /**
     * Execute the console command.
     */
    public function handle(ResourceService $resourceService): int
    {
        $fromDate = $this->option('from') ?: '2026-09-22';
        $closingId = $this->option('closing');
        $includeOpen = (bool) $this->option('include-open');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Iniciando auditoría y corrección de cierres COP desde: {$fromDate}");
        if ($dryRun) {
            $this->warn('--- MODO SIMULACIÓN (DRY-RUN) ACTIVO: NO SE GUARDARÁN CAMBIOS ---');
        }

        $rateCop = $resourceService->getExchangeRate('COP');
        if ($rateCop < 100) {
            $rateCop = 3200.0;
        }

        $query = CashClosing::query();

        if ($closingId) {
            $query->where('id', (int) $closingId);
        } else {
            $query->where('closing_date', '>=', $fromDate);
            if (!$includeOpen) {
                $query->where('status', CashClosing::CLOSED);
            }
        }

        $closings = $query->orderBy('closing_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $this->info("Se encontraron {$closings->count()} cierres para analizar.");

        $totalOriginalSpare = 0.0;
        $totalAdjustedSpare = 0.0;
        $affectedClosingsCount = 0;
        $affectedOrdersCount = 0;

        DB::beginTransaction();

        try {
            foreach ($closings as $closing) {
                $orders = Order::where('cash_closing_id', $closing->id)
                    ->where('status', Order::COMPLETED)
                    ->get();

                $realCopCash = 0.0;
                $hasModifiedOrders = false;

                foreach ($orders as $order) {
                    $orderCurrency = strtoupper((string) ($order->currency ?? 'USD'));
                    $orderPayments = is_array($order->payment_methods) ? $order->payment_methods : json_decode((string) $order->payment_methods, true);

                    // 1. Detectar y corregir orden con total_amount desfasado en COP (ej. total_amount = 100 con valor USD real)
                    if ($orderCurrency === 'COP') {
                        $currentAmount = (float) $order->total_amount;
                        $usdAmount = (float) $order->total_amount_usd;

                        // Si el total registrado es <= 100 pero en USD es mayor a $0.10
                        if ($currentAmount <= 100.0 && $usdAmount > 0.10) {
                            $calculatedCop = ceil(round($usdAmount * $rateCop) / 100) * 100;

                            if (!$dryRun) {
                                $order->total_amount = $calculatedCop;
                                $order->save();
                            }

                            $affectedOrdersCount++;
                            $hasModifiedOrders = true;
                        }
                    }

                    // 2. Extraer el efectivo neto cobrado en cash_cop
                    if (!empty($orderPayments) && is_array($orderPayments)) {
                        foreach ($orderPayments as $payment) {
                            $method = strtolower((string) ($payment['method'] ?? ''));
                            $payAmount = (float) ($payment['amount'] ?? 0);

                            if ($method === 'cash_cop') {
                                // Descontar vuelto de la orden si fue dado en COP
                                $returns = (float) ($order->money_returns ?? 0);
                                $netCash = max(0, $payAmount - $returns);
                                $realCopCash += $netCash;
                            }
                        }
                    }
                }

                // 3. Evaluar si el cierre requiere ajuste de sobrante y cop_cash
                $currentCopCash = (float) $closing->cop_cash;
                $declaredCop = (float) ($closing->declared_cop ?? 0);
                $originalSpare = (float) ($closing->cop_spare ?? 0);

                $totalOriginalSpare += $originalSpare;

                // Descontar conversiones COP si se entregó vuelto en COP para ventas en USD
                $copConversion = (float) ($closing->cop_conversion ?? 0);
                $netClosingCopCash = round($realCopCash - $copConversion, 2);

                // Si se encontró efectivo real en órdenes superior al registrado en cop_cash o el cop_cash actual está desfasado
                if ($netClosingCopCash != $currentCopCash || $hasModifiedOrders || $currentCopCash < 0) {
                    $newCopCash = $netClosingCopCash;
                    $newDiffCop = round($declaredCop - $newCopCash, 2);
                    $newSpare = max(0.0, $newDiffCop);
                    $newDelivered = round($newCopCash + $newSpare, 2);

                    $totalAdjustedSpare += $newSpare;
                    $affectedClosingsCount++;

                    $this->line(sprintf(
                        "Cierre #%d (%s | Estado: %s): COP Sistema: %.2f -> %.2f | Declarado: %.2f | Sobrante: %.2f -> %.2f",
                        $closing->id,
                        Carbon::parse($closing->closing_date)->format('Y-m-d'),
                        $closing->status,
                        $currentCopCash,
                        $newCopCash,
                        $declaredCop,
                        $originalSpare,
                        $newSpare
                    ));

                    if (!$dryRun) {
                        $closing->cop_cash = $newCopCash;
                        $closing->diff_cop = $newDiffCop;
                        $closing->cop_spare = $newSpare;
                        $closing->cop_delivered = $newDelivered;
                        $closing->recalculateTotals();
                        $closing->save();
                    }
                } else {
                    $totalAdjustedSpare += $originalSpare;
                }
            }

            // 4. Sincronizar cierres diarios afectados
            if (!$dryRun) {
                $dailyClosures = DailyCashClosure::where('created_at', '>=', Carbon::parse($fromDate)->startOfDay())
                    ->with('cashClosings')
                    ->get();

                foreach ($dailyClosures as $daily) {
                    $boxes = $daily->cashClosings;
                    if ($boxes->isEmpty()) {
                        continue;
                    }

                    $daily->update([
                        'cop_cash'      => round((float) $boxes->sum('cop_cash'), 2),
                        'cop_spare'     => round((float) $boxes->sum('cop_spare'), 2),
                        'cop_delivered' => round((float) $boxes->sum('cop_delivered'), 2),
                        'total_cop'     => round((float) $boxes->sum('total_cop'), 2),
                        'total_sales'   => round((float) $boxes->sum('total_sales'), 2),
                    ]);
                }

                DB::commit();
                $this->info('Cambios confirmados y aplicados exitosamente en la base de datos.');
            } else {
                DB::rollBack();
                $this->warn('Simulación finalizada. No se alteró ningún registro.');
            }

            $this->newLine();
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Órdenes corregidas', $affectedOrdersCount],
                    ['Cierres ajustados', $affectedClosingsCount],
                    ['Sobrante COP antes del ajuste', number_format($totalOriginalSpare, 2, ',', '.') . ' COP'],
                    ['Sobrante COP después del ajuste', number_format($totalAdjustedSpare, 2, ',', '.') . ' COP'],
                    ['Diferencia recalculada (Ventas reales)', number_format($totalOriginalSpare - $totalAdjustedSpare, 2, ',', '.') . ' COP'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error ejecutando corrección: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
