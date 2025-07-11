<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\ExpiredLog;
use Illuminate\Support\Facades\Auth;
use Log;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     * Se ejecuta cuando se crea un nuevo producto.
     */
    public function created(Product $product): void
    {
        Log::info('Product created: ' . $product);
        if ($product->stock && $product->stock > 0) {
            $this->createInventoryMovement([
                'product_id' => $product->id,
                'product_lot_id' => null,
                'movement_type' => 'purchase',
                'quantity' => $product->stock,
                'invoice_id' => null,
                'supplier_id' => $product->supplier_id,
                'order_id' => null,
                'user_id' => Auth::id(),
                'stock_before' => 0,
                'stock_after' => $product->stock,
                'movement_date' => now(),
            ]);
        }
    }

    /**
     * Handle the Product "updated" event.
     * Se ejecuta cuando se actualiza un producto.
     */
    public function updated(Product $product): void
    {
        if ($product->isDirty('stock') && !$this->isStockChangeFromLots($product)) {
            $originalStock = $product->getOriginal('stock') ?? 0;
            $newStock = $product->stock ?? 0;
            $difference = $newStock - $originalStock;

            if ($difference != 0) {
                $movementType = $difference > 0 ? 'purchase' : 'adjustment';

                $this->createInventoryMovement([
                    'product_id' => $product->id,
                    'product_lot_id' => null,
                    'movement_type' => $movementType,
                    'quantity' => $difference,
                    'invoice_id' => null,
                    'supplier_id' => $product->supplier_id,
                    'order_id' => null,
                    'user_id' => Auth::id(),
                    'stock_before' => $originalStock,
                    'stock_after' => $newStock,
                    'movement_date' => now(),
                ]);
            }
        }
    }

    /**
     * Verificar si el cambio de stock es causado por ProductLotObserver
     */
    private function isStockChangeFromLots(Product $product): bool
    {
        return InventoryMovement::where('product_id', $product->id)
            ->whereNotNull('product_lot_id')
            ->where('created_at', '>=', now()->subMinutes(1))
            ->exists();
    }

    /**
     * 
     */
    public static function handleOrderMovement(Order $order): void
    {
        foreach ($order->details as $detail) {
            $product = $detail->product;
            $stockBefore = $product->stock ?? 0;
            $stockAfter = $stockBefore - $detail->quantity;

            $product->update(['stock' => $stockAfter]);

            static::createInventoryMovement([
                'product_id' => $product->id,
                'product_lot_id' => $detail->product_lot_id ?? null,
                'movement_type' => 'sale',
                'quantity' => -$detail->quantity,
                'invoice_id' => null,
                'supplier_id' => null,
                'order_id' => $order->id,
                'user_id' => $order->seller_id,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'movement_date' => $order->order_date ?? now(),
            ]);
        }
    }

    /**
     *
     */
    public static function handleExpiredProduct(ExpiredLog $expiredLog): void
    {
        $product = $expiredLog->product;
        $stockBefore = $product->stock ?? 0;

        static::createInventoryMovement([
            'product_id' => $expiredLog->product_id,
            'product_lot_id' => $expiredLog->lot_id,
            'movement_type' => 'expired',
            'quantity' => -$expiredLog->expired_quantity,
            'invoice_id' => null,
            'supplier_id' => null,
            'order_id' => null,
            'user_id' => null,
            'stock_before' => $stockBefore,
            'stock_after' => $stockBefore - $expiredLog->expired_quantity,
            'movement_date' => now(),
        ]);
    }

    /**
     * Crear movimiento de inventario para facturas (compras)
     */
    public static function handleInvoiceMovement(Invoice $invoice): void
    {
        foreach ($invoice->details as $detail) {
            $product = $detail->product;
            $stockBefore = $product->stock ?? 0;
            $stockAfter = $stockBefore + $detail->quantity;

            $product->update(['stock' => $stockAfter]);

            static::createInventoryMovement([
                'product_id' => $product->id,
                'product_lot_id' => $detail->product_lot_id ?? null,
                'movement_type' => 'purchase',
                'quantity' => $detail->quantity,
                'invoice_id' => $invoice->id,
                'supplier_id' => $invoice->supplier_id,
                'order_id' => null,
                'user_id' => $invoice->registered_by,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'movement_date' => $invoice->received_date ?? now(),
            ]);
        }
    }

    /**
     * Crear registro de movimiento de inventario
     */
    private static function createInventoryMovement(array $data): InventoryMovement
    {
        return InventoryMovement::create($data);
    }
}
