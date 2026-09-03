<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\InventoryMovement;
use App\Models\ExpiredLog;
use App\Models\ProductCount;
use App\Models\ProductDistribution;
use Illuminate\Support\Facades\Auth;

class ProductLotObserver
{
    /**
     * Flags de compatibilidad (mantenidos para retrocompatibilidad).
     */
    public static bool $isExpiringLot = false;
    public static bool $isReturningLot = false;
    public static bool $skipMovementCreation = false;

    /**
     * Handle the ProductLot "created" event.
     */
    public function created(ProductLot $productLot): void
    {
        $this->updateProductStockAndPrice($productLot->product);
    }

    /**
     * Handle the ProductLot "updated" event.
     */
    public function updated(ProductLot $productLot): void
    {
        if ($productLot->isDirty('quantity') || $productLot->isDirty('unit_cost')) {
            $this->updateProductStockAndPrice($productLot->product);
        }
    }

    /**
     * Handle the ProductLot "deleted" event.
     */
    public function deleted(ProductLot $productLot): void
    {
        $this->updateProductStockAndPrice($productLot->product);
    }

    /**
     * Handle the ProductLot "restored" event.
     */
    public function restored(ProductLot $productLot): void
    {
        $this->updateProductStockAndPrice($productLot->product);
    }

    /**
     * Handle the ProductLot "force deleted" event.
     */
    public function forceDeleted(ProductLot $productLot): void
    {
        $this->updateProductStockAndPrice($productLot->product);
    }

    /**
     * Recalcula y actualiza el stock total del producto desde la suma de lotes activos.
     */
    protected function updateProductStockAndPrice(?Product $product): void
    {
        if (!$product) {
            return;
        }

        $totalStock = (float) $product->lots()->where('quantity', '>', 0)->sum('quantity');
        $product->updateQuietly(['stock' => $totalStock]);
        \App\Services\Inventory\StockoutService::syncStockout($product, $totalStock);
    }
}
