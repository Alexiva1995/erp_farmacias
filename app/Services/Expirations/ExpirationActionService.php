<?php

namespace App\Services\Expirations;

use App\Models\ExpiredLog;
use App\Models\ProductLot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ExpirationActionService
{
    /**
     * Marca un lote como caducado, redistribuye su costo y registra la acción.
     *
     * @param ProductLot $lot
     * @throws Exception Si el lote no tiene unidades o si algo falla en la transacción.
     */
    public function expireLot(ProductLot $lot): void
    {
        if ($lot->quantity <= 0) {
            throw new Exception('Este lote ya no tiene unidades.', 400);
        }

        DB::beginTransaction();

        try {
            $quantityToExpire = $lot->quantity;
            $costPerUnit = $lot->cost_price;
            $totalLostValue = $quantityToExpire * $costPerUnit;

            $lot->quantity = 0;
            $lot->save();

            $this->redistributeCost($lot->product_id, $lot->id, $totalLostValue);

            ExpiredLog::create([
                'lot_id' => $lot->id,
                'product_id' => $lot->product_id,
                'product_name' => $lot->product->name,
                'lot_number' => $lot->lot_number,
                'expired_quantity' => $quantityToExpire,
                'cost_per_unit' => $costPerUnit,
                'total_lost_value' => $totalLostValue,
            ]);

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar la caducidad del lote: ' . $e->getMessage());
            throw new Exception('Error al procesar la caducidad del lote.', 500, $e);
        }
    }

    /**
     * Helper para redistribuir el costo entre los lotes restantes de un producto.
     */
    private function redistributeCost(int $productId, int $excludedLotId, float $totalLostValue): void
    {
        $remainingStock = ProductLot::where('product_id', $productId)
            ->where('id', '!=', $excludedLotId)
            ->sum('quantity');

        if ($remainingStock > 0) {
            $costAdjustmentPerUnit = $totalLostValue / $remainingStock;
            ProductLot::where('product_id', $productId)
                ->where('id', '!=', $excludedLotId)
                ->where('quantity', '>', 0)
                ->increment('cost_price', $costAdjustmentPerUnit);
        }
    }
}
