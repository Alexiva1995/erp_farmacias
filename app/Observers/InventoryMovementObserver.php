<?php

namespace App\Observers;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductLot;

class InventoryMovementObserver
{
    /**
     * Flag estático para omitir validación en migraciones o comandos de normalización.
     */
    public static bool $skipValidation = false;

    /**
     * Handle the InventoryMovement "creating" event (Blindaje de consistencia Kardex).
     */
    public function creating(InventoryMovement $movement): void
    {
        if (static::$skipValidation) {
            return;
        }

        $productId = $movement->product_id;
        if (!$productId) {
            return;
        }

        $qty = (float) $movement->quantity;
        $before = (float) $movement->stock_before;
        $after = (float) $movement->stock_after;

        // 1. Validar ecuación contable: stock_after == stock_before + quantity
        $expectedAfter = round($before + $qty, 4);
        if (abs($after - $expectedAfter) > 0.001) {
            throw new \InvalidArgumentException(
                "Bloqueo de trazabilidad: La ecuación de balance no cuadra (stock_before: {$before} + cantidad: {$qty} != stock_after: {$after}) para el producto ID #{$productId}."
            );
        }

        // 2. Validar coincidencia con la sumatoria de lotes si existen lotes registrados
        $hasLots = ProductLot::where('product_id', $productId)->exists();
        if ($hasLots) {
            $totalLotStock = round((float) ProductLot::where('product_id', $productId)->sum('quantity'), 4);
            if (abs($after - $totalLotStock) > 0.001) {
                throw new \InvalidArgumentException(
                    "Bloqueo de trazabilidad: El saldo final ({$after}) no coincide con el stock real en lotes ({$totalLotStock}) para el producto ID #{$productId}."
                );
            }
        }
    }

    /**
     * Handle the InventoryMovement "created" event.
     */
    public function created(InventoryMovement $movement): void
    {
        $this->syncProductStock($movement->product_id);
    }

    /**
     * Handle the InventoryMovement "deleted" event.
     */
    public function deleted(InventoryMovement $movement): void
    {
        $this->syncProductStock($movement->product_id);
    }

    /**
     * Sincroniza la columna estática stock con el stock real acumulado por lotes.
     */
    private function syncProductStock(?int $productId): void
    {
        if (!$productId) {
            return;
        }

        $product = Product::find($productId);
        if ($product) {
            $totalStock = (float) $product->lots()->sum('quantity');
            $product->updateQuietly(['stock' => $totalStock]);
            \App\Services\Inventory\StockoutService::syncStockout($product, $totalStock);
        }
    }
}
