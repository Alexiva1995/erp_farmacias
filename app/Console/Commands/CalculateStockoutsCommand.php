<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalculateStockoutsCommand extends Command
{
    protected $signature = 'inventory:calculate-quiebres';
    protected $description = 'Calcula los dias de quiebre exactos en los ultimos 90 dias usando la tabla de trazabilidad (inventory_movements)';

    public function handle()
    {
        $this->info("Iniciando calculo de quiebres por trazabilidad mensual (M1, M2, M3)...");

        $now = now();
        $dateM1 = $now->copy()->subDays(30);
        $dateM2 = $now->copy()->subDays(60);
        $dateM3 = $now->copy()->subDays(90);
        $date90dStr = $dateM3->format('Y-m-d H:i:s');

        Product::where('is_deleted', 0)
            ->where('is_scarce', 0)
            ->chunk(200, function ($products) use ($dateM1, $dateM2, $dateM3, $date90dStr, $now) {
                $productIds = $products->pluck('id')->toArray();
                
                $movements = DB::table('inventory_movements')
                    ->whereIn('product_id', $productIds)
                    ->where('movement_date', '>=', $date90dStr)
                    ->orderBy('product_id')
                    ->orderBy('movement_date', 'asc')
                    ->get();
                    
                $grouped = $movements->groupBy('product_id');

                foreach ($products as $product) {
                    $pid = $product->id;
                    $productMoves = $grouped->get($pid);
                    
                    // Toparlo a la edad del producto
                    $ageDays = Carbon::parse($product->created_at)->diffInDays($now);
                    $ageDays = max(1, min($ageDays, 90));

                    $ageM1 = min(30, $ageDays);
                    $ageM2 = min(30, max(0, $ageDays - 30));
                    $ageM3 = min(30, max(0, $ageDays - 60));

                    if (!$productMoves || $productMoves->isEmpty()) {
                        // Sin movimientos en 90 dias
                        $qM1 = ($product->stock <= 0) ? $ageM1 : 0;
                        $qM2 = ($product->stock <= 0) ? $ageM2 : 0;
                        $qM3 = ($product->stock <= 0) ? $ageM3 : 0;
                        $qTotal = $qM1 + $qM2 + $qM3;

                        DB::table('products')->where('id', $pid)->update([
                            'dias_quiebre_m1'        => $qM1,
                            'dias_quiebre_m2'        => $qM2,
                            'dias_quiebre_m3'        => $qM3,
                            'dias_quiebre_90d_cache' => $qTotal,
                        ]);
                        continue;
                    }

                    $intervals = [];
                    $lastZeroDate = null;

                    // Estado inicial antes del inicio del periodo (hace 90 días)
                    $firstMove = $productMoves->first();
                    if ($firstMove->stock_before <= 0) {
                        $lastZeroDate = $dateM3->copy();
                    }

                    foreach ($productMoves as $move) {
                        $moveDate = Carbon::parse($move->movement_date);

                        // Si cayó a 0
                        if ($move->stock_after <= 0 && $lastZeroDate === null) {
                            $lastZeroDate = $moveDate->copy();
                        } 
                        // Si salió de 0
                        elseif ($move->stock_after > 0 && $lastZeroDate !== null) {
                            $intervals[] = [$lastZeroDate, $moveDate->copy()];
                            $lastZeroDate = null; 
                        }
                    }

                    // Si al final del periodo sigue en 0
                    if ($lastZeroDate !== null) {
                        $intervals[] = [$lastZeroDate, $now->copy()];
                    }

                    // Función auxiliar para calcular horas de solapamiento con una ventana
                    $calcOverlapHours = function ($startWindow, $endWindow) use ($intervals) {
                        $totalHours = 0;
                        foreach ($intervals as [$iStart, $iEnd]) {
                            $overlapStart = $iStart->greaterThan($startWindow) ? $iStart : $startWindow;
                            $overlapEnd   = $iEnd->lessThan($endWindow) ? $iEnd : $endWindow;

                            if ($overlapStart->lessThan($overlapEnd)) {
                                $totalHours += $overlapStart->diffInHours($overlapEnd);
                            }
                        }
                        return $totalHours;
                    };

                    $hM3 = $calcOverlapHours($dateM3, $dateM2);
                    $hM2 = $calcOverlapHours($dateM2, $dateM1);
                    $hM1 = $calcOverlapHours($dateM1, $now);

                    $qM3 = min($ageM3, (int)round($hM3 / 24));
                    $qM2 = min($ageM2, (int)round($hM2 / 24));
                    $qM1 = min($ageM1, (int)round($hM1 / 24));
                    $qTotal = min($ageDays, $qM1 + $qM2 + $qM3);

                    DB::table('products')->where('id', $pid)->update([
                        'dias_quiebre_m1'        => $qM1,
                        'dias_quiebre_m2'        => $qM2,
                        'dias_quiebre_m3'        => $qM3,
                        'dias_quiebre_90d_cache' => $qTotal,
                    ]);
                }
            });

        $this->info("Calculo mensual de quiebres finalizado correctamente.");
    }
}
