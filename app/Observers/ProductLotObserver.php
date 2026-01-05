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
                // Solo crear movimiento de caducado si viene de inventory/expirations
                // Si no, crear movimiento de pérdida o ajuste según el origen
                $this->handleZeroQuantityMovement($productLot, $originalQuantity);
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
            'movement_date' => now(),
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
     * Manejar movimiento cuando un lote pasa de tener cantidad a 0
     * Solo se registra como 'expired' (caducado) si viene de inventory/expirations
     * Si viene de inventario cíclico, se registra como 'loss' (pérdida)
     * Si no viene de ninguno, se registra como 'loss' (pérdida) por defecto
     */
    protected function handleZeroQuantityMovement(ProductLot $productLot, int $expiredQuantity)
    {
        $product = $productLot->product;

        // Verificar si viene de inventory/expirations (ExpiredLog reciente)
        $recentExpiredLog = \App\Models\ExpiredLog::where('lot_id', $productLot->id)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        // Verificar si viene de un inventario cíclico
        // Buscamos un ProductCount que está siendo procesado (pending o approved) para el mismo producto
        $recentProductCount = \App\Models\ProductCount::where('product_id', $product->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->orderBy('updated_at', 'desc')
            ->first();

        // Si encontramos un ProductCount reciente, verificamos si tiene un ProductDistribution para este lote
        $isFromInventoryCycle = false;
        if ($recentProductCount) {
            // Verificar si ya existe el ProductDistribution
            $productDistribution = \App\Models\ProductDistribution::where('product_count_id', $recentProductCount->id)
                ->where('product_lot_id', $productLot->id)
                ->first();
            
            // Si existe el ProductDistribution o el ProductCount es reciente, asumimos que viene del inventario cíclico
            if ($productDistribution || $recentProductCount->updated_at >= now()->subMinutes(2)) {
                $isFromInventoryCycle = true;
            }
        }

        // Solo usar 'expired' (caducado) si viene específicamente de inventory/expirations
        // Si viene de inventario cíclico o de otro lugar, usar 'loss' (pérdida)
        if ($recentExpiredLog) {
            $movementType = 'expired';
        } else {
            // Por defecto, si no viene de expirations, es pérdida (no caducado)
            $movementType = 'loss';
        }

        $existingMovement = InventoryMovement::where('product_lot_id', $productLot->id)
            ->where('movement_type', $movementType)
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
            'movement_type' => $movementType,
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
     * Si viene de un inventario cíclico y es negativo, siempre es 'loss' (pérdida)
     * Si la diferencia es negativa (hace falta), se registra como 'loss' (pérdida)
     * Si la diferencia es positiva, se registra como 'adjustment' (ajuste)
     */
    protected function createAdjustmentMovement(ProductLot $productLot, int $originalQuantity, int $newQuantity)
    {
        $product = $productLot->product;

        $stockBefore = $product->lots()->sum('quantity') + ($originalQuantity - $productLot->quantity);
        $quantityDifference = $newQuantity - $originalQuantity;
        $stockAfter = $stockBefore + $quantityDifference;

        // Verificar si viene de un inventario cíclico (ProductCount aprobado)
        $recentProductDistribution = \App\Models\ProductDistribution::where('product_lot_id', $productLot->id)
            ->whereHas('productCount', function ($query) {
                $query->where('status', 'approved');
            })
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        // Si viene de inventario cíclico y es negativo, siempre es pérdida
        // Si no viene de inventario cíclico: negativo = pérdida, positivo = ajuste
        if ($recentProductDistribution && $quantityDifference < 0) {
            $movementType = 'loss';
        } else {
            $movementType = $quantityDifference < 0 ? 'loss' : 'adjustment';
        }

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => $productLot->id,
            'movement_type' => $movementType,
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
