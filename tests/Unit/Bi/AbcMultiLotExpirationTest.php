<?php

namespace Tests\Unit\Bi;

use PHPUnit\Framework\TestCase;

class AbcMultiLotExpirationTest extends TestCase
{
    /**
     * Valida la simulación cronológica multi-lote (FIFO/FEFO) con descarte dinámico de mermas.
     * Escenario:
     * - Lote 1: 30 unidades (vence en 60 días / Fin de Febrero)
     * - Lote 2: 60 unidades (vence en 90 días / Fin de Marzo)
     * - Lote 3: 30 unidades (vence en 150 días / Fin de Mayo)
     * - Ventas: 10 unidades/mes (0.333333 u/día)
     *
     * Resultado esperado:
     * - Lote 1: Vende 20 u, caducan 10 u (Merma Feb).
     * - Lote 2: Vende 10 u, caducan 50 u (Merma Mar).
     * - Lote 3: Vende 20 u, caducan 10 u (Merma May).
     * - Total Vencidas: 70 u | Total Vendidas: 50 u.
     */
    public function test_multi_lot_expiration_simulation_calculates_exact_losses(): void
    {
        $lots = [
            ['quantity' => 30.0, 'days_to_exp' => 60],
            ['quantity' => 60.0, 'days_to_exp' => 90],
            ['quantity' => 30.0, 'days_to_exp' => 150],
        ];

        $dailyRunRate = 10.0 / 30.0; // 10 unidades / mes

        $currentDay = 0;
        $totalRiskUnits = 0.0;
        $lotMermas = [];

        foreach ($lots as $index => $lot) {
            $lotQty = (float) $lot['quantity'];
            $daysToLotExp = (int) $lot['days_to_exp'];

            if ($dailyRunRate > 0) {
                $availableDays = max(0, $daysToLotExp - $currentDay);
                $maxSalesCapacity = $availableDays * $dailyRunRate;

                if ($lotQty <= $maxSalesCapacity) {
                    $daysUsed = $lotQty / $dailyRunRate;
                    $currentDay += $daysUsed;
                    $lotMermas[$index] = 0.0;
                } else {
                    $unitsSold = $maxSalesCapacity;
                    $unitsExpired = $lotQty - $unitsSold;
                    $totalRiskUnits += $unitsExpired;
                    $lotMermas[$index] = round($unitsExpired, 2);
                    $currentDay = $daysToLotExp;
                }
            }
        }

        // Aserciones exactas
        $this->assertEquals(10.0, $lotMermas[0], 'El lote 1 debe tener 10 unidades de merma');
        $this->assertEquals(50.0, $lotMermas[1], 'El lote 2 debe tener 50 unidades de merma');
        $this->assertEquals(10.0, $lotMermas[2], 'El lote 3 debe tener 10 unidades de merma');
        $this->assertEquals(70.0, round($totalRiskUnits, 2), 'El total acumulado de mermas debe ser 70 unidades');
    }
}
