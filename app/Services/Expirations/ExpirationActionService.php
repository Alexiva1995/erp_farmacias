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
     * Marca un lote como caducado y registra la acción.
     * YA NO redistribuye el costo automáticamente.
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
            $costPerUnit = $lot->unit_cost;
            $totalLostValue = $quantityToExpire * $costPerUnit;

            // Marcar el lote con cantidad 0
            $lot->quantity = 0;
            $lot->save();

            // YA NO se redistribuye el costo automáticamente
            // $this->redistributeCost($lot->product_id, $lot->id, $totalLostValue);

            // Registrar en el log de caducados
            ExpiredLog::create([
                'lot_id' => $lot->id,
                'product_id' => $lot->product_id,
                'product_name' => $lot->product->name,
                'lot_number' => $lot->lot_number ?? null,
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
     * Reajusta el precio de un lote específico distribuyendo el costo
     * de los lotes caducados entre los lotes activos.
     *
     * @param ProductLot $lot
     * @throws Exception Si algo falla en la transacción.
     */
    public function adjustLotPrice(ProductLot $lot): void
    {
        if ($lot->quantity <= 0) {
            throw new Exception('No se puede reajustar el precio de un lote sin unidades.', 400);
        }

        DB::beginTransaction();

        try {
            $productId = $lot->product_id;

            // Calcular el valor total perdido de los lotes caducados del mismo producto
            $totalLostValue = ExpiredLog::where('product_id', $productId)
                ->whereNotIn('lot_id', function ($query) use ($productId) {
                    $query->select('lot_id')
                        ->from('price_adjustment_logs')
                        ->where('product_id', $productId);
                })
                ->sum('total_lost_value');

            if ($totalLostValue <= 0) {
                throw new Exception('No hay costos de lotes caducados para redistribuir.', 400);
            }

            // Redistribuir el costo entre los lotes activos
            $this->redistributeCost($productId, null, $totalLostValue);

            // Marcar los lotes caducados como ya procesados
            DB::table('price_adjustment_logs')->insert(
                ExpiredLog::where('product_id', $productId)
                    ->whereNotIn('lot_id', function ($query) use ($productId) {
                        $query->select('lot_id')
                            ->from('price_adjustment_logs')
                            ->where('product_id', $productId);
                    })
                    ->get(['lot_id', 'product_id'])
                    ->map(function ($log) {
                        return [
                            'lot_id' => $log->lot_id,
                            'product_id' => $log->product_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })
                    ->toArray()
            );

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al reajustar el precio del lote: ' . $e->getMessage());
            throw new Exception('Error al reajustar el precio del lote.', 500, $e);
        }
    }

    /**
     * Reajusta los precios de múltiples lotes.
     *
     * @param array $lotIds
     * @return array
     */
    public function adjustMultipleLotsPrices(array $lotIds): array
    {
        $failedLots = [];
        $successCount = 0;
        $processedProductIds = [];

        $lots = ProductLot::whereIn('id', $lotIds)
            ->where('quantity', '>', 0)
            ->get();

        $foundIds = $lots->pluck('id')->all();
        $notFoundIds = array_diff($lotIds, $foundIds);

        if (!empty($notFoundIds)) {
            foreach ($notFoundIds as $id) {
                $failedLots[] = [
                    'id' => $id,
                    'error' => 'Lote no encontrado o sin unidades.',
                ];
            }
        }

        // Agrupar lotes por producto para procesar una vez por producto
        $lotsByProduct = $lots->groupBy('product_id');

        foreach ($lotsByProduct as $productId => $productLots) {
            try {
                // Calcular el valor total perdido para este producto
                $totalLostValue = ExpiredLog::where('product_id', $productId)
                    ->whereNotIn('lot_id', function ($query) use ($productId) {
                        $query->select('lot_id')
                            ->from('price_adjustment_logs')
                            ->where('product_id', $productId);
                    })
                    ->sum('total_lost_value');

                if ($totalLostValue > 0) {
                    DB::beginTransaction();

                    // Redistribuir el costo
                    $this->redistributeCost($productId, null, $totalLostValue);

                    // Marcar como procesados
                    DB::table('price_adjustment_logs')->insert(
                        ExpiredLog::where('product_id', $productId)
                            ->whereNotIn('lot_id', function ($query) use ($productId) {
                                $query->select('lot_id')
                                    ->from('price_adjustment_logs')
                                    ->where('product_id', $productId);
                            })
                            ->get(['lot_id', 'product_id'])
                            ->map(function ($log) {
                                return [
                                    'lot_id' => $log->lot_id,
                                    'product_id' => $log->product_id,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            })
                            ->toArray()
                    );

                    DB::commit();

                    $successCount += $productLots->count();
                    $processedProductIds[] = $productId;
                } else {
                    foreach ($productLots as $lot) {
                        $failedLots[] = [
                            'id' => $lot->id,
                            'error' => 'No hay costos de lotes caducados para redistribuir.',
                        ];
                    }
                }
            } catch (Exception $e) {
                DB::rollBack();
                foreach ($productLots as $lot) {
                    $failedLots[] = [
                        'id' => $lot->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'success_count' => $successCount,
            'failed_lots' => $failedLots,
            'processed_products' => count($processedProductIds),
        ];
    }

    /**
     * Helper para redistribuir el costo entre los lotes restantes de un producto.
     * 
     * @param int $productId
     * @param int|null $excludedLotId
     * @param float $totalLostValue
     */
    private function redistributeCost(int $productId, ?int $excludedLotId, float $totalLostValue): void
    {
        $query = ProductLot::where('product_id', $productId)
            ->where('quantity', '>', 0);

        if ($excludedLotId !== null) {
            $query->where('id', '!=', $excludedLotId);
        }

        $remainingStock = $query->sum('quantity');

        if ($remainingStock > 0) {
            $costAdjustmentPerUnit = $totalLostValue / $remainingStock;

            $updateQuery = ProductLot::where('product_id', $productId)
                ->where('quantity', '>', 0);

            if ($excludedLotId !== null) {
                $updateQuery->where('id', '!=', $excludedLotId);
            }

            $updateQuery->increment('unit_cost', $costAdjustmentPerUnit);
        }
    }

    /**
     * Procesa múltiples lotes para caducarlos.
     * 
     * @param array $lotIds
     * @return array
     */
    public function expireMultipleLots(array $lotIds): array
    {
        $failedLots = [];
        $successCount = 0;

        $lots = ProductLot::whereIn('id', $lotIds)->get();

        $foundIds = $lots->pluck('id')->all();
        $notFoundIds = array_diff($lotIds, $foundIds);

        if (!empty($notFoundIds)) {
            foreach ($notFoundIds as $id) {
                $failedLots[] = [
                    'id' => $id,
                    'error' => 'Lote no encontrado.',
                ];
            }
        }

        foreach ($lots as $lot) {
            try {
                $this->expireLot($lot);
                $successCount++;
            } catch (Exception $e) {
                $failedLots[] = [
                    'id' => $lot->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success_count' => $successCount,
            'failed_lots' => $failedLots,
        ];
    }
}
