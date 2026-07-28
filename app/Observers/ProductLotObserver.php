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
     * Flag estático para indicar cuando una modificación de lote proviene explícitamente del proceso de caducidad.
     */
    public static bool $isExpiringLot = false;

    /**
     * Handle the ProductLot "created" event.
     */
    public function created(ProductLot $productLot)
    {
        $recentMovement = InventoryMovement::where('product_id', $productLot->product_id)
            ->where('movement_type', 'purchase')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->where(function ($query) use ($productLot) {
                $query->where('product_lot_id', $productLot->id)
                    ->orWhereNull('product_lot_id');
            })
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
            $originalQuantity = (float) ($productLot->getOriginal('quantity') ?? 0);
            $newQuantity = (float) ($productLot->quantity ?? 0);

            // Si hay un movimiento de venta reciente (en los últimos 2 minutos), no duplicar movimiento
            $recentSaleMovement = InventoryMovement::where('product_id', $productLot->product_id)
                ->where('movement_type', 'sale')
                ->where(function ($query) use ($productLot) {
                    $query->where('product_lot_id', $productLot->id)
                        ->orWhereNull('product_lot_id');
                })
                ->where('created_at', '>=', now()->subMinutes(2))
                ->exists();

            if ($recentSaleMovement) {
                $this->updateProductStockAndPrice($productLot->product);
                return;
            }

            // Si ya existe un movimiento reciente de caducidad o ajuste para este lote, omitir duplicación
            $recentExpiredMovement = InventoryMovement::where('product_lot_id', $productLot->id)
                ->where('movement_type', 'expired')
                ->where('created_at', '>=', now()->subMinutes(2))
                ->exists();

            if ($recentExpiredMovement) {
                $this->updateProductStockAndPrice($productLot->product);
                return;
            }

            if ($originalQuantity > 0 && $newQuantity === 0) {
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
     * Manejar movimiento cuando un lote pasa de tener cantidad a 0.
     */
    protected function handleZeroQuantityMovement(ProductLot $productLot, float $expiredQuantity)
    {
        $product = $productLot->product;

        $isExpiring = static::$isExpiringLot;

        if (!$isExpiring) {
            $isExpiring = ExpiredLog::where(function ($query) use ($productLot) {
                    $query->where('lot_id', $productLot->id)
                        ->orWhere('product_id', $productLot->product_id);
                })
                ->where('created_at', '>=', now()->subMinutes(15))
                ->exists();
        }

        $recentProductCount = ProductCount::where('product_id', $product->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->orderBy('updated_at', 'desc')
            ->first();

        $movementType = $isExpiring ? 'expired' : 'loss';

        $existingMovement = InventoryMovement::where('product_lot_id', $productLot->id)
            ->whereIn('movement_type', ['expired', 'loss'])
            ->where('created_at', '>=', now()->subMinute())
            ->first();

        if ($existingMovement) {
            if ($isExpiring && $existingMovement->getRawOriginal('movement_type') !== 'expired') {
                $existingMovement->update(['movement_type' => 'expired']);
            }
            return;
        }

        $stockBefore = $product->lots()->sum('quantity') + ($expiredQuantity - $productLot->quantity);
        $stockAfter = max(0, $stockBefore - $expiredQuantity);

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => $productLot->id,
            'movement_type' => $movementType,
            'quantity' => -$expiredQuantity,
            'invoice_id' => null,
            'supplier_id' => null,
            'order_id' => null,
            'user_id' => Auth::id(),
            'product_count_id' => $recentProductCount ? $recentProductCount->id : null,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
        ]);
    }

    /**
     * Crear movimiento de ajuste para otros cambios de cantidad.
     */
    protected function createAdjustmentMovement(ProductLot $productLot, float $originalQuantity, float $newQuantity)
    {
        $product = $productLot->product;

        $stockBefore = $product->lots()->sum('quantity') + ($originalQuantity - $productLot->quantity);
        $quantityDifference = $newQuantity - $originalQuantity;
        $stockAfter = max(0, $stockBefore + $quantityDifference);

        $recentProductDistribution = ProductDistribution::where('product_lot_id', $productLot->id)
            ->whereHas('productCount', function ($query) {
                $query->where('status', 'approved');
            })
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

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
            'product_count_id' => $recentProductDistribution ? $recentProductDistribution->product_count_id : null,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
        ]);
    }

    /**
     * Recalcula y actualiza el stock total del producto desde la suma de lotes.
     */
    protected function updateProductStockAndPrice(Product $product)
    {
        $recentOrderedInvoice = InventoryMovement::where('product_id', $product->id)
            ->whereNotNull('invoice_id')
            ->whereHas('invoice', function ($query) {
                $query->where('status', 'ordered');
            })
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($recentOrderedInvoice) {
            $totalStock = $product->lots()->sum('quantity');
            $product->updateQuietly(['stock' => $totalStock]);
            \App\Services\Inventory\StockoutService::syncStockout($product, $totalStock);
            return;
        }

        $product->load('lots');
        $totalStock = $product->lots()->sum('quantity');

        $product->updateQuietly(['stock' => $totalStock]);
        \App\Services\Inventory\StockoutService::syncStockout($product, $totalStock);
    }
}
