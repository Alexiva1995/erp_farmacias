<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\Auth;

class ProductLotObserver
{
    /**
     * Handle the ProductLot "created" event.
     */
    public function created(ProductLot $productLot)
    {
        $this->createPurchaseMovement($productLot);

        $this->updateProductStockAndPrice($productLot->product);
    }

    /**
     * Handle the ProductLot "updated" event.
     */
    public function updated(ProductLot $productLot)
    {
        if ($productLot->isDirty('quantity') || $productLot->isDirty('unit_cost')) {
            if ($productLot->isDirty('quantity')) {
                $this->createAdjustmentMovement($productLot);
            }

            $this->updateProductStockAndPrice($productLot->product);
        }
    }

    /**
     * Handle the ProductLot "deleted" event.
     */
    public function deleted(ProductLot $productLot)
    {
        $this->createDeletionMovement($productLot);

        $this->updateProductStockAndPrice($productLot->product);
    }

    /**
     * Handle the ProductLot "restored" event.
     */
    public function restored(ProductLot $productLot)
    {
        $this->createRestorationMovement($productLot);

        $this->updateProductStockAndPrice($productLot->product);
    }

    /**
     * Handle the ProductLot "force deleted" event.
     */
    public function forceDeleted(ProductLot $productLot)
    {
        $this->createDeletionMovement($productLot);

        $this->updateProductStockAndPrice($productLot->product);
    }

    /**
     * Crear movimiento de compra cuando se crea un lote
     */
    protected function createPurchaseMovement(ProductLot $productLot)
    {
        $product = $productLot->product;
        $stockBefore = $product->stock ?? 0;
        $stockAfter = $stockBefore + $productLot->quantity;

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => $productLot->id,
            'movement_type' => 'purchase',
            'quantity' => $productLot->quantity,
            'invoice_id' => $productLot->invoice_id ?? null,
            'supplier_id' => $productLot->supplier_id ?? $product->supplier_id,
            'order_id' => null,
            'user_id' => Auth::id(),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => $productLot->created_at ?? now(),
        ]);
    }

    /**
     * Crear movimiento de ajuste cuando se actualiza un lote
     */
    protected function createAdjustmentMovement(ProductLot $productLot)
    {
        $product = $productLot->product;
        $originalQuantity = $productLot->getOriginal('quantity') ?? 0;
        $newQuantity = $productLot->quantity ?? 0;
        $difference = $newQuantity - $originalQuantity;

        $stockBefore = $product->stock ?? 0;
        $stockAfter = $stockBefore + $difference;

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => $productLot->id,
            'movement_type' => 'adjustment',
            'quantity' => $difference,
            'invoice_id' => null,
            'supplier_id' => null,
            'order_id' => null,
            'user_id' => Auth::id(),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
        ]);
    }

    /**
     * Crear movimiento cuando se elimina un lote
     */
    protected function createDeletionMovement(ProductLot $productLot)
    {
        $product = $productLot->product;
        $stockBefore = $product->stock ?? 0;
        $stockAfter = $stockBefore - $productLot->quantity;

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => $productLot->id,
            'movement_type' => 'adjustment',
            'quantity' => -$productLot->quantity,
            'invoice_id' => null,
            'supplier_id' => null,
            'order_id' => null,
            'user_id' => Auth::id(),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
        ]);
    }

    /**
     * Crear movimiento cuando se restaura un lote
     */
    protected function createRestorationMovement(ProductLot $productLot)
    {
        $product = $productLot->product;
        $stockBefore = $product->stock ?? 0;
        $stockAfter = $stockBefore + $productLot->quantity;

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => $productLot->id,
            'movement_type' => 'adjustment',
            'quantity' => $productLot->quantity,
            'invoice_id' => null,
            'supplier_id' => null,
            'order_id' => null,
            'user_id' => Auth::id(),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
        ]);
    }

    /**
     * Recalcula y actualiza el stock total y precio promedio ponderado del producto.
     */
    protected function updateProductStockAndPrice(Product $product)
    {
        $totalStock = $product->lots()->sum('quantity');

        $lots = $product->lots()
            ->where('quantity', '>', 0)
            ->whereNotNull('unit_cost')
            ->where('unit_cost', '>', 0)
            ->get();

        if ($lots->isEmpty()) {
            $product->updateQuietly(['stock' => $totalStock]);
            return;
        }

        $totalValue = 0;
        $totalQuantityWithCost = 0;

        foreach ($lots as $lot) {
            $totalValue += ($lot->quantity * $lot->unit_cost);
            $totalQuantityWithCost += $lot->quantity;
        }

        $averagePrice = $totalQuantityWithCost > 0 ? $totalValue / $totalQuantityWithCost : $product->sale_price;

        $product->updateQuietly([
            'stock' => $totalStock,
            'sale_price' => round($averagePrice, 2) // Redondear a 2 decimales
        ]);
    }
}
