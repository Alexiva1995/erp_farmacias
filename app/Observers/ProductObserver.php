<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\ReturnEntry;
use App\Exceptions\InsufficientStockException;
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
     * Manejar movimientos de órdenes (ventas).
     * La única fuente de verdad es SUM(product_lots.quantity).
     * Product.stock se sincroniza desde los lotes; si el resultado fuera negativo, se lanza InsufficientStockException.
     */
    public static function handleOrderMovement(Order $order): void
    {
        if (InventoryMovement::where('order_id', $order->id)->exists()) {
            return;
        }

        foreach ($order->details as $detail) {
            $product = $detail->product;
            if (!$product) {
                continue;
            }
            $stockBefore = $product->stock ?? 0;
            $lotsSum = (int) $product->lots()->sum('quantity');
            $stockAfter = $lotsSum;

            if ($stockAfter < 0) {
                throw new InsufficientStockException(
                    $product->name ?? 'Producto',
                    max(0, $lotsSum),
                    (int) $detail->quantity,
                    "Inconsistencia de inventario: la suma de lotes para '{$product->name}' es negativa ({$lotsSum})."
                );
            }

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
     * Manejar movimientos de facturas (compras).
     * Se llama únicamente desde InvoiceActionService::approveInvoice (cuando la factura
     * pasa de 'cargada' a 'ordenada para pago'). No se debe llamar al cargar la factura
     * ni al archivarla (ordered).
     */
    public static function handleInvoiceMovement(Invoice $invoice): void
    {
        foreach ($invoice->details as $detail) {
            $product = $detail->product;
            // Cargar relación profitability si no está cargada
            if (!$product->relationLoaded('profitability')) {
                $product->load('profitability');
            }
            $stockBefore = $product->stock ?? 0;
            $stockAfter = $stockBefore + $detail->quantity;

            // Calcular costo promedio ponderado usando products.unit_cost como fuente de verdad
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

            // Calcular precio de venta usando rentabilidad
            $profitabilityPercentage = self::getProfitabilityPercentage($product);
            $newSalePrice = $newUnitCost * (1 + ($profitabilityPercentage / 100));

            Product::withoutEvents(function () use ($product, $stockAfter, $newUnitCost, $newSalePrice) {
                $product->update([
                    'stock' => $stockAfter,
                    'unit_cost' => $newUnitCost,
                    'sale_price' => $newSalePrice
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
     * Obtener el porcentaje de rentabilidad para un producto
     */
    private static function getProfitabilityPercentage(Product $product): float
    {
        // Si el producto tiene rentabilidad bloqueada, usar ese porcentaje
        if ($product->profitability && $product->profitability->is_locked) {
            return (float) $product->profitability->profitability_percentage;
        }

        // Si no, usar el porcentaje por defecto del sistema
        $profitabilitySetting = \App\Models\ProfitabilitySetting::orderBy('id', 'desc')->first();
        return $profitabilitySetting ? (float) $profitabilitySetting->default_profitability_percentage : 0;
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
