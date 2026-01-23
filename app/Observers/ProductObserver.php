<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\ReturnEntry;
use Illuminate\Support\Facades\Auth;

class ProductObserver
{
    /**
     * Handle the Product "updated" event.
     * SOLO para cambios manuales de stock (sin lotes involucrados)
     */
    public function updated(Product $product): void
    {
        if ($product->isDirty('stock') && !$product->lots()->exists()) {
            $originalStock = $product->getOriginal('stock') ?? 0;
            $newStock = $product->stock ?? 0;
            $difference = $newStock - $originalStock;

            if ($difference != 0) {
                $movementType = $difference > 0 ? 'purchase' : 'adjustment';

                InventoryMovement::create([
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
     * Manejar movimientos de órdenes (ventas)
     */
    public static function handleOrderMovement(Order $order): void
    {
        foreach ($order->details as $detail) {
            $product = $detail->product;
            $stockBefore = $product->stock ?? 0;
            $stockAfter = $stockBefore - $detail->quantity;

            Product::withoutEvents(function () use ($product, $stockAfter) {
                $product->update(['stock' => $stockAfter]);
            });

            InventoryMovement::create([
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
                'movement_date' => now(),
            ]);
        }
    }

    /**
     * Manejar movimientos de facturas (compras)
     */
    public static function handleInvoiceMovement(Invoice $invoice): void
    {
        foreach ($invoice->details as $detail) {
            $product = $detail->product;
            $stockBefore = $product->stock ?? 0;
            $stockAfter = $stockBefore + $detail->quantity;

            // Calcular costo promedio ponderado
            $currentUnitCost = $product->unit_cost ?? 0;
            $rate = $invoice->exchange_rate > 0 ? $invoice->exchange_rate : 1;
            $incomingUnitCost = $detail->unit_cost;

            // Convertir a base (USD) si la factura no está en USD
            if ($invoice->currency !== 'USD') {
                $incomingUnitCost = $incomingUnitCost / $rate;
            }

            // Calcular nuevo costo promedio
            // (StockActual * CostoActual) + (CantidadEntrante * CostoEntrante) / StockTotal
            $totalValueBefore = $stockBefore * $currentUnitCost;
            $totalValueIncoming = $detail->quantity * $incomingUnitCost;
            $newUnitCost = 0;

            if ($stockAfter > 0) {
                $newUnitCost = ($totalValueBefore + $totalValueIncoming) / $stockAfter;
            } else {
                $newUnitCost = $incomingUnitCost; // Safe fallback
            }

            Product::withoutEvents(function () use ($product, $stockAfter, $newUnitCost) {
                $product->update([
                    'stock' => $stockAfter,
                    'unit_cost' => $newUnitCost
                ]);
            });

            // Buscar el lote por producto, número de lote y fecha de expiración si no hay product_lot_id
            $productLotId = $detail->product_lot_id ?? null;

            InventoryMovement::create([
                'product_id' => $product->id,
                'product_lot_id' => $productLotId,
                'movement_type' => 'purchase',
                'quantity' => $detail->quantity,
                'invoice_id' => $invoice->id,
                'supplier_id' => $invoice->supplier_id,
                'order_id' => null,
                'user_id' => $invoice->registered_by,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'movement_date' => now(),
            ]);
        }
    }

    /**
     * Manejar movimientos de devolución
     */
    public static function handleReturnMovement(ReturnEntry $return): void
    {

        $product = $return->product;
        $stockBefore = $product->stock ?? 0;
        $stockAfter = $stockBefore + $return->quantity;

        Product::withoutEvents(function () use ($product, $stockAfter) {
            $product->update(['stock' => $stockAfter]);
        });

        InventoryMovement::create([
            'product_id' => $product->id,
            'product_lot_id' => null,
            'movement_type' => 'return',
            'quantity' => $return->quantity,
            'invoice_id' => null,
            'supplier_id' => null,
            'order_id' => $return->order_id,
            'user_id' => $return->generated_by_id ?? Auth::id(),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'movement_date' => now(),
        ]);

    }
}
