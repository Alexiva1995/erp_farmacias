<?php

declare(strict_types=1);

namespace App\Services\Expirations;

use App\Models\ExpiredLog;
use App\Models\PriceAdjustmentLog;
use App\Models\ProductLot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ExpirationActionService
{
    /**
     * Marca un lote como caducado y registra la acción.
     *
     *
     * @param ProductLot $lot
     * @throws Exception
     */
    public function expireLot(ProductLot $lot): void
    {
        if ($lot->quantity <= 0) {
            throw new Exception('Este lote ya no tiene unidades.', 400);
        }

        DB::beginTransaction();

        try {
            // Load product relationship if not already loaded
            if (!$lot->relationLoaded('product')) {
                $lot->load('product');
            }

            $quantityToExpire = $lot->quantity;
            // Use product unit_cost if lot unit_cost is null or 0
            $costPerUnit = $lot->unit_cost && $lot->unit_cost > 0 
                ? $lot->unit_cost 
                : ($lot->product->unit_cost ?? 0);
            $totalLostValue = $quantityToExpire * $costPerUnit;

            $lot->quantity = 0;

            ExpiredLog::create([
                'lot_id' => $lot->id,
                'product_id' => $lot->product_id,
                'product_name' => $lot->product->name,
                'lot_number' => $lot->lot_number ?? null,
                'expired_quantity' => $quantityToExpire,
                'cost_per_unit' => $costPerUnit,
                'total_lost_value' => $totalLostValue,
            ]);

            $lot->save();

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar la caducidad del lote: ' . $e->getMessage());
            throw new Exception('Error al procesar la caducidad del lote.', 500, $e);
        }
    }
    public function getAdjustmentPreview(string $month, array $excludedProductIds = []): array
    {
        if ($this->hasMonthPriceAdjustment($month)) {
            throw new Exception('Ya se ha realizado un reajuste de precios para este mes.', 409);
        }

        $formatField = DB::getDriverName() === 'sqlite' 
            ? "strftime('%Y-%m', created_at)" 
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $allExpiredLogs = ExpiredLog::whereRaw("$formatField = ?", [$month])
            ->get();

        if ($allExpiredLogs->isEmpty()) {
            throw new Exception('No se encontraron productos caducados para el mes especificado.', 404);
        }

        $logsToProcess = $allExpiredLogs->filter(function ($log) use ($excludedProductIds) {
            return !in_array($log->product_id, $excludedProductIds);
        });

        if ($logsToProcess->isEmpty()) {
            throw new Exception('Todos los productos del mes han sido excluidos. No hay nada que reajustar.', 400);
        }

        $totalLostValue = $logsToProcess->sum('total_lost_value');

        if ($totalLostValue <= 0) {
            throw new Exception('No hay valor perdido para redistribuir.', 400);
        }

        $totalActiveStock = DB::table('products')
            ->where('stock', '>', 0)
            ->whereNotIn('id', $excludedProductIds)
            ->sum('stock');

        if ($totalActiveStock <= 0) {
            throw new Exception('No hay stock activo para redistribuir el costo (considerando las exclusiones).', 400);
        }

        $costAdjustmentPerUnit = $totalLostValue / $totalActiveStock;

        $affectedProductsCount = DB::table('products')
            ->where('stock', '>', 0)
            ->whereNotIn('id', $excludedProductIds)
            ->count();

        return [
            'total_lost_value' => $totalLostValue,
            'total_active_stock' => (int) $totalActiveStock,
            'cost_adjustment_per_unit' => $costAdjustmentPerUnit,
            'affected_products_count' => $affectedProductsCount,
        ];
    }
    /**
     * Reajusta los precios de TODOS los productos caducados de un mes,
     *
     * 
     * @param string $month Formato Y-m
     * @param array $excludedProductIds Array de IDs de productos a excluir
     * @return array
     * @throws Exception
     */
    public function adjustExpiredProductsPricesWithExclusions(string $month, array $excludedProductIds = []): array
    {
        if ($this->hasMonthPriceAdjustment($month)) {
            return [
                'success' => false,
                'message' => 'Ya se ha realizado un reajuste de precios para este mes.'
            ];
        }

        DB::beginTransaction();

        try {
            $formatField = DB::getDriverName() === 'sqlite' 
                ? "strftime('%Y-%m', created_at)" 
                : "DATE_FORMAT(created_at, '%Y-%m')";

            $allExpiredLogs = ExpiredLog::whereRaw("$formatField = ?", [$month])
                ->get();

            if ($allExpiredLogs->isEmpty()) {
                throw new Exception('No se encontraron productos caducados para el mes especificado.', 400);
            }

            $logsToProcess = $allExpiredLogs->filter(function ($log) use ($excludedProductIds) {
                return !in_array($log->product_id, $excludedProductIds);
            });

            $excludedLogs = $allExpiredLogs->filter(function ($log) use ($excludedProductIds) {
                return in_array($log->product_id, $excludedProductIds);
            });

            if ($logsToProcess->isEmpty()) {
                throw new Exception('Todos los productos del mes han sido excluidos del reajuste.', 400);
            }

            $totalLostValue = $logsToProcess->sum('total_lost_value');

            if ($totalLostValue <= 0) {
                throw new Exception('No hay valor perdido para redistribuir.', 400);
            }

            $totalActiveStock = DB::table('products')
                ->where('stock', '>', 0)
                ->whereNotIn('id', $excludedProductIds)
                ->sum('stock');

            if ($totalActiveStock <= 0) {
                throw new Exception('No hay stock activo para redistribuir el costo (excluyendo productos seleccionados).', 400);
            }

            $costAdjustmentPerUnit = $totalLostValue / $totalActiveStock;

            $activeProductIds = DB::table('products')
                ->where('stock', '>', 0)
                ->whereNotIn('id', $excludedProductIds)
                ->pluck('id');

            foreach ($activeProductIds as $productId) {
                $productStock = DB::table('products')
                    ->where('id', $productId)
                    ->value('stock');

                if ($productStock > 0) {
                    $currentUnitCost = DB::table('products')
                        ->where('id', $productId)
                        ->value('unit_cost');

                    $newUnitCost = $currentUnitCost + $costAdjustmentPerUnit;

                    $updated = DB::table('products')
                        ->where('id', $productId)
                        ->update(['unit_cost' => $newUnitCost]);
                }
            }

            foreach ($logsToProcess as $log) {
                PriceAdjustmentLog::create([
                    'month' => $month,
                    'expired_log_id' => $log->id,
                    'product_id' => $log->product_id,
                    'product_name' => $log->product_name,
                    'lot_id' => $log->lot_id,
                    'lot_number' => $log->lot_number,
                    'cost_redistributed' => $log->total_lost_value,
                    'processed_by' => Auth::id(),
                    'processed_at' => now(),
                ]);
            }

            DB::commit();

            $processedCount = $logsToProcess->count();
            $excludedCount = $excludedLogs->count();
            $totalCount = $allExpiredLogs->count();

            $totalUnitsProcessed = $logsToProcess->sum('expired_quantity');
            $affectedProductCount = count($activeProductIds);

            return [
                'success' => true,
                'message' => "Reajuste realizado con éxito.",
                'processed_logs' => $processedCount,
                'excluded_logs' => $excludedCount,
                'total_logs' => $totalCount,
                'total_units_processed' => $totalUnitsProcessed,
                'total_cost_redistributed' => $totalLostValue,
                'total_units_affected' => $totalActiveStock,
                'affected_products_count' => $affectedProductCount,
                'cost_per_unit' => $costAdjustmentPerUnit
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al reajustar precios de productos caducados: ' . $e->getMessage());
            throw new Exception('Error al reajustar precios de productos caducados.', 500, $e);
        }
    }

    /**
     * Verifica si ya se realizó un reajuste de precios en un mes específico.
     *
     * @param string $month
     * @return bool
     */
    public function hasMonthPriceAdjustment(string $month): bool
    {
        // El usuario solicita poder usar el botón de reajustar precio las veces que quiera sin inhabilitarlo
        return false;
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

    /**
     * Método original para compatibilidad hacia atrás (DEPRECATED)
     * @deprecated Use adjustExpiredProductsPricesWithExclusions instead
     */
    public function adjustExpiredProductsPrices(string $month, array $expiredLogIds): array
    {
        $formatField = DB::getDriverName() === 'sqlite' 
            ? "strftime('%Y-%m', created_at)" 
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $allLogsInMonth = ExpiredLog::whereRaw("$formatField = ?", [$month])
            ->pluck('product_id')
            ->unique()
            ->toArray();

        $processedProductIds = ExpiredLog::whereIn('id', $expiredLogIds)
            ->pluck('product_id')
            ->unique()
            ->toArray();

        $excludedProductIds = array_diff($allLogsInMonth, $processedProductIds);

        return $this->adjustExpiredProductsPricesWithExclusions($month, $excludedProductIds);
    }

    /**
     * @deprecated
     */
    public function adjustLotPrice(ProductLot $lot): void
    {
        throw new Exception('Esta funcionalidad ha sido deshabilitada.', 400);
    }

    public function adjustMultipleLotsPrices(array $lotIds): array
    {
        throw new Exception('Esta funcionalidad ha sido deshabilitada.', 400);
    }
}
