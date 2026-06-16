<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductStockout;
use Carbon\Carbon;

/**
 * Servicio StockoutService
 *
 * Se encarga de gestionar los registros de quiebres de stock (product_stockouts)
 * cuando cambia la cantidad de stock de un producto.
 */
class StockoutService
{
    /**
     * Sincroniza el estado de quiebre de stock de un producto basándose en su nuevo nivel de existencias.
     *
     * @param Product $product
     * @param float $newStock
     * @return void
     */
    public static function syncStockout(Product $product, float $newStock): void
    {
        if ($newStock <= 0) {
            // Si el stock es <= 0, verificar si ya existe un quiebre activo (sin fecha de fin)
            $activeStockout = ProductStockout::where('product_id', $product->id)
                ->whereNull('end_date')
                ->first();

            if (!$activeStockout) {
                ProductStockout::create([
                    'product_id' => $product->id,
                    'start_date' => Carbon::now(),
                ]);
            }
        } else {
            // Si el stock es > 0, cerrar cualquier quiebre activo
            $activeStockout = ProductStockout::where('product_id', $product->id)
                ->whereNull('end_date')
                ->first();

            if ($activeStockout) {
                $now = Carbon::now();
                $startDate = Carbon::parse($activeStockout->start_date);
                
                // Calcular la diferencia en días con precisión decimal (segundos / 86400)
                $seconds = $startDate->diffInSeconds($now);
                $days = $seconds / 86400.0;

                $activeStockout->update([
                    'end_date' => $now,
                    'days_out_of_stock' => round($days, 4),
                ]);
            }
        }
    }
}
