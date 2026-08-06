<?php

namespace App\Observers;

use App\Models\InventoryMovement;
use App\Models\Product;

class InventoryMovementObserver
{
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
