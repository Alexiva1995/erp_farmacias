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
        // Verificar si ya existe un movimiento reciente (dentro de 2 minutos) para este producto y lote
        // Esto evita crear movimientos duplicados cuando se ordena una factura
        // (handleInvoiceMovement crea los movimientos con invoice_id)
        $recentMovement = \App\Models\InventoryMovement::where('product_id', $productLot->product_id)
            ->where('product_lot_id', $productLot->id)
            ->where('movement_type', 'purchase')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if (!$recentMovement) {
            $this->createPurchaseMovement($productLot);
        }

        $this->updateProductStockAndPrice($productLot->product);
    }

    /**
     * Handle the ProductLot "updated" event.
     */
    public function updated(ProductLot $productLot)
    {
        if ($productLot->isDirty('quantity')) {
            $originalQuantity = $productLot->getOriginal('quantity') ?? 0;
            $newQuantity = $productLot->quantity ?? 0;

            // Check if there's a sale movement created recently (within 2 minutes) for this product
            // This indicates the lot quantity change is part of a sale, not an expiration
            // The sale movement is created by ProductObserver::handleOrderMovement after lot updates
            // We check by product_id since product_lot_id might be null in sale movements
            $recentSaleMovement = \App\Models\InventoryMovement::where('product_id', $productLot->product_id)
                ->where('movement_type', 'sale')
                ->where(function ($query) use ($productLot) {
                    $query->where('product_lot_id', $productLot->id)
                          ->orWhereNull('product_lot_id');
                })
                ->where('created_at', '>=', now()->subMinutes(2))
                ->exists();

            if ($recentSaleMovement) {
                // If there's a recent sale movement, don't create expired/adjustment movements
                // Just update the product stock and price
                $this->updateProductStockAndPrice($productLot->product);
                return;
            }


            if ($originalQuantity > 0 && $newQuantity === 0) {
                $this->createExpiredMovement($productLot, $originalQuantity);
            } elseif ($originalQuantity !== $newQuantity) {
                $this->createAdjustmentMovement($productLot, $originalQuantity, $newQuantity);
            }

            $this->updateProductStockAndPrice($productLot->product);
        } elseif ($productLot->isDirty('unit_cost')) {
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

        $stockBefore = $product->lots()->where('id', '!=', $productLot->id)->sum('quantity');
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
     * Crear movimiento cuando se elimina un lote
     */
    protected function createDeletionMovement(ProductLot $productLot)
    {
        $product = $productLot->product;

        $stockBefore = $product->lots()->where('id', '!=', $productLot->id)->sum('quantity') + $productLot->quantity;
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

        $stockBefore = $product->lots()->where('id', '!=', $productLot->id)->sum('quantity');
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
     * Crear movimiento de caducidad cuando un lote pasa de tener cantidad a 0
     */
    protected function createExpiredMovement(ProductLot $productLot, int $expiredQuantity)
    {
        $product = $productLot->product;

        $existingMovement = InventoryMovement::where('product_lot_id', $productLot->id)
            ->where('movement_type', 'expired')
            ->where('quantity', -$expiredQuantity)
            ->where('created_at', '>=', now()->subMinute())
            ->first();

        if ($existingMovement) {
            return;
        }

        $stockBefore = $product->lots()->sum('quantity') + ($expiredQuantity - $productLot->quantity);
        $stockAfter = $stockBefore - $expiredQuantity;

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => $productLot->id,
            'movement_type' => 'expired',
            'quantity' => -$expiredQuantity,
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
     * Crear movimiento de ajuste para otros cambios de cantidad
     */
    protected function createAdjustmentMovement(ProductLot $productLot, int $originalQuantity, int $newQuantity)
    {
        $product = $productLot->product;

        $stockBefore = $product->lots()->sum('quantity') + ($originalQuantity - $productLot->quantity);
        $quantityDifference = $newQuantity - $originalQuantity;
        $stockAfter = $stockBefore + $quantityDifference;

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => $productLot->id,
            'movement_type' => 'adjustment',
            'quantity' => $quantityDifference,
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
     * Recalcula y actualiza el stock total y costo promedio ponderado del producto.
     */
    protected function updateProductStockAndPrice(Product $product)
    {
        $product->load('lots');

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

        $averageCost = $totalQuantityWithCost > 0 ? $totalValue / $totalQuantityWithCost : $product->cost;

        $product->updateQuietly([
            'stock' => $totalStock,
            'unit_cost' => round($averageCost, 2)
        ]);
    }
}
