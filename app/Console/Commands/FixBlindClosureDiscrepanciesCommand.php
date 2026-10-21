<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CashClosing;
use App\Models\DailyCashClosure;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixBlindClosureDiscrepanciesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-blind-closure-discrepancies {--from=2026-09-01 : Fecha inicial para revisión} {--dry-run : Ejecutar en modo simulación sin guardar en base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige los sobrantes anómalos de cierre ciego analizando la nota de auditoría original (blind_note) y el efectivo real esperado en caja.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromDate = $this->option('from') ?: '2026-09-01';
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Iniciando auditoría y corrección de discrepancias en cierres ciegos desde: {$fromDate}");
        if ($dryRun) {
            $this->warn('--- MODO SIMULACIÓN (DRY-RUN) ACTIVO: NO SE GUARDARÁN CAMBIOS ---');
        }

        $closings = CashClosing::where('closing_date', '>=', $fromDate)
            ->where('status', CashClosing::CLOSED)
            ->whereNotNull('blind_note')
            ->orderBy('closing_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $this->info("Se encontraron {$closings->count()} cierres con notas de cierre ciego.");

        $adjustedCount = 0;
        $totalOriginalSpare = 0.0;
        $totalNewSpare = 0.0;

        DB::beginTransaction();

        try {
            foreach ($closings as $closing) {
                $currentSpare = (float) ($closing->cop_spare ?? 0);
                $totalOriginalSpare += $currentSpare;

                // Solo analizar cierres con sobrantes registrados significativos
                if ($currentSpare <= 0) {
                    $totalNewSpare += $currentSpare;
                    continue;
                }

                $blindNote = (string) $closing->blind_note;
                $declaredCop = (float) ($closing->declared_cop ?? 0);

                // Buscar el valor teórico del sistema que se mostró en pantalla en la nota original
                // Ej: "COP Físico: Declarado 272,300.00 / Sistema 268,700.00"
                $extractedSysCop = null;
                if (preg_match('/COP.+?Sistema\s*([\d,\.]+)/i', $blindNote, $matches)) {
                    $cleanSys = str_replace(',', '', $matches[1]);
                    $extractedSysCop = (float) $cleanSys;
                }

                $newSpare = $currentSpare;
                $newDiffCop = (float) ($closing->diff_cop ?? 0);
                $shouldUpdate = false;

                // Caso A: Si la nota original registró un valor teórico de sistema y difiere abismalmente de cop_cash
                if ($extractedSysCop !== null && $extractedSysCop > 0) {
                    $realDiff = round($declaredCop - $extractedSysCop, 2);
                    $calculatedSpare = max(0.0, $realDiff);

                    // Si el sobrante guardado actual es mayor al calculado contra la nota por más de $1.000 COP
                    if ($currentSpare > ($calculatedSpare + 1000.0)) {
                        $newSpare = $calculatedSpare;
                        $newDiffCop = $realDiff;
                        $shouldUpdate = true;
                    }
                }

                // Caso B: Detección de error de digitación por cero adicional (ej. 115.000 vs 11.500)
                $copCash = (float) $closing->cop_cash;
                if ($copCash > 0 && abs($declaredCop - ($copCash * 10)) < 1.0) {
                    // El cajero declaró exactamente 10 veces más que las ventas (agregó un cero)
                    $this->warn("Posible cero de más detectado en Cierre #{$closing->id}: Declarado {$declaredCop} vs Sistema {$copCash}");
                    $newSpare = 0.0;
                    $newDiffCop = 0.0;
                    $declaredCop = $copCash;
                    $shouldUpdate = true;
                }

                if ($shouldUpdate) {
                    $adjustedCount++;
                    $totalNewSpare += $newSpare;

                    $this->line(sprintf(
                        "Cierre #%d (%s): Declarado: %.2f | Sobrante: %.2f -> %.2f (Diferencia corregida: -%.2f COP)",
                        $closing->id,
                        Carbon::parse($closing->closing_date)->format('Y-m-d'),
                        $declaredCop,
                        $currentSpare,
                        $newSpare,
                        $currentSpare - $newSpare
                    ));

                    if (!$dryRun) {
                        $closing->cop_spare = $newSpare;
                        $closing->diff_cop = $newDiffCop;
                        $closing->cop_delivered = round($closing->cop_cash + ($closing->cop_cash_payment_credit ?? 0) + $newSpare, 2);
                        $closing->save();
                    }
                } else {
                    $totalNewSpare += $currentSpare;
                }
            }

            // Sincronizar cierres diarios
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
                    ]);
                }

                DB::commit();
                $this->info('Correcciones aplicadas exitosamente en la base de datos.');
            } else {
                DB::rollBack();
                $this->warn('Simulación finalizada. No se alteró ningún registro.');
            }

            $this->newLine();
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Cierres ajustados', $adjustedCount],
                    ['Sobrante COP antes del ajuste', number_format($totalOriginalSpare, 2, ',', '.') . ' COP'],
                    ['Sobrante COP después del ajuste', number_format($totalNewSpare, 2, ',', '.') . ' COP'],
                    ['Exceso de sobrante eliminado', number_format($totalOriginalSpare - $totalNewSpare, 2, ',', '.') . ' COP'],
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
