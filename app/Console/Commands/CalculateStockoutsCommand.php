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
        $this->info("Iniciando calculo de quiebres por trazabilidad...");

        $now = now();
        $date90d = $now->copy()->subDays(90)->format('Y-m-d H:i:s');

        Product::where('is_deleted', 0)
            ->where('is_scarce', 0)
            ->chunk(200, function ($products) use ($date90d, $now) {
                $productIds = $products->pluck('id')->toArray();
                
                $movements = DB::table('inventory_movements')
                    ->whereIn('product_id', $productIds)
                    ->where('movement_date', '>=', $date90d)
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

                    if (!$productMoves || $productMoves->isEmpty()) {
                        // Sin movimientos en 90 dias: o siempre tuvo, o siempre estuvo en 0
                        $diasQuiebre = ($product->stock <= 0) ? $ageDays : 0;
                        DB::table('products')->where('id', $pid)->update(['dias_quiebre_90d_cache' => $diasQuiebre]);
                        continue;
                    }

                    $totalQuiebreHoras = 0;
                    $lastZeroDate = null;

                    // Estado antes del primer movimiento del periodo de 90 dias
                    $firstMove = $productMoves->first();
                    if ($firstMove->stock_before <= 0) {
                        $lastZeroDate = Carbon::parse($date90d);
                    }

                    foreach ($productMoves as $move) {
                        $moveDate = Carbon::parse($move->movement_date);

                        // Si pasó a 0
                        if ($move->stock_after <= 0 && $lastZeroDate === null) {
                            $lastZeroDate = $moveDate;
                        } 
                        // Si salió de 0
                        elseif ($move->stock_after > 0 && $lastZeroDate !== null) {
                            $totalQuiebreHoras += $lastZeroDate->diffInHours($moveDate);
                            $lastZeroDate = null; 
                        }
                    }

                    // Si al final del periodo sigue en 0
                    if ($lastZeroDate !== null) {
                        $totalQuiebreHoras += $lastZeroDate->diffInHours($now);
                    }

                    $diasQuiebre = round($totalQuiebreHoras / 24);
                    $diasQuiebre = min($diasQuiebre, $ageDays);

                    DB::table('products')->where('id', $pid)->update(['dias_quiebre_90d_cache' => $diasQuiebre]);
                }
            });

        $this->info("Calculo finalizado correctamente.");
    }
}
