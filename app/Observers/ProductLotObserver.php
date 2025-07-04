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
        // 1. Crear movimiento de inventario tipo "purchase"
        $this->createPurchaseMovement($productLot);

        // 2. Actualizar stock del producto
        $this->updateProductStock($productLot->product);
    }

    /**
     * Handle the ProductLot "updated" event.
     */
    public function updated(ProductLot $productLot)
    {
        if ($productLot->isDirty('quantity')) {
            // 1. Crear movimiento de inventario tipo "adjustment"
            $this->createAdjustmentMovement($productLot);

            // 2. Actualizar stock del producto
            $this->updateProductStock($productLot->product);
        }
    }

    /**
     * Handle the ProductLot "deleted" event.
     */
    public function deleted(ProductLot $productLot)
    {
        // 1. Crear movimiento de inventario para la eliminación
        $this->createDeletionMovement($productLot);

        // 2. Actualizar stock del producto
        $this->updateProductStock($productLot->product);
    }

    /**
     * Handle the ProductLot "restored" event.
     */
    public function restored(ProductLot $productLot)
    {
        // 1. Crear movimiento de inventario para la restauración
        $this->createRestorationMovement($productLot);

        // 2. Actualizar stock del producto
        $this->updateProductStock($productLot->product);
    }

    /**
     * Handle the ProductLot "force deleted" event.
     */
    public function forceDeleted(ProductLot $productLot)
    {
        // 1. Crear movimiento de inventario para la eliminación permanente
        $this->createDeletionMovement($productLot);

        // 2. Actualizar stock del producto
        $this->updateProductStock($productLot->product);
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
     * Recalcula y actualiza el stock total del producto asociado.
     */
    protected function updateProductStock(Product $product)
    {
        $totalStock = $product->lots()->sum('quantity');
        $product->updateQuietly(['stock' => $totalStock]);
    }
}
