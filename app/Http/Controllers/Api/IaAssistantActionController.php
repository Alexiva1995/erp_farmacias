<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Http\Requests\IaAssistant\AddToOrderRequest;
use App\Http\Requests\IaAssistant\AddMultipleToOrderRequest;
use App\Http\Requests\IaAssistant\UpdateManualQuantityRequest;
use App\Http\Requests\IaAssistant\UpdateBarcodeRequest;
use App\Services\Products\ProductActionService;
use App\Enums\AutoOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IaAssistantActionController extends Controller
{
    public function __construct(
        private ProductActionService $productActionService
    ) {}

    /**
     * Añade un producto a la auto-orden de un proveedor.
     * Si no tiene proveedor enlazado y se proporciona uno, se crea el enlace.
     */
    public function addToOrder(AddToOrderRequest $request): JsonResponse
    {
        $productId = $request->product_id;
        $quantity = $request->quantity;
        $supplierId = $request->supplier_id;
        $productSupplierId = $request->product_supplier_id;
        $customUnitCost = $request->unit_cost;

        DB::beginTransaction();
        try {
            $product = Product::findOrFail($productId);

            // Si es origen colombiano (COL), forzar la adición a la orden del proveedor ID 48
            if ((int)$product->is_colombian_origin === 1) {
                $supplierId = 48;
                $productSupplierId = null;
            }

            // 1. Obtener el enlace producto-proveedor
            $ps = null;
            if ($productSupplierId) {
                $ps = ProductSupplier::find($productSupplierId);
            }

            if (!$ps && $supplierId) {
                $ps = ProductSupplier::where('product_id', $productId)
                    ->where('supplier_id', $supplierId)
                    ->first();
            }

            if (!$ps && $supplierId) {
                // Crear enlace básico si no existe
                $ps = ProductSupplier::create([
                    'product_id' => $productId,
                    'supplier_id' => $supplierId,
                    'unit_cost' => $product->unit_cost ?? 0,
                    'unit_cost_usd' => $product->unit_cost ?? 0,
                ]);
            }

            if (!$ps) {
                return response()->json([
                    'message' => 'No se pudo determinar el proveedor para este producto.',
                    'debug' => [
                        'product_id' => $productId,
                        'supplier_id' => $supplierId,
                        'product_supplier_id' => $productSupplierId
                    ]
                ], 422);
            }

            $supplierId = $ps->supplier_id;
            $productSupplierId = $ps->id;

            // Si se proporciona un costo unitario exacto desde el frontend (respetando filtros), se usa.
            // De lo contrario, se aplica la prioridad por defecto: Costo con descuento > Costo normal > Costo del producto base.
            $unitCost = $customUnitCost !== null && $customUnitCost > 0
                ? (float) $customUnitCost
                : ($ps->unit_cost_usd_with_discount > 0 
                    ? $ps->unit_cost_usd_with_discount 
                    : ($ps->unit_cost_usd > 0 ? $ps->unit_cost_usd : $product->unit_cost));

            // Si el costo de compra tiene un incremento mayor al 20% respecto al costo local, bloquear el precio base
            $costoProveedor = (float)$unitCost;
            $costoLocal = (float)($product->unit_cost ?? 0);
            if ($costoLocal > 0 && ((($costoProveedor - $costoLocal) / $costoLocal) * 100) > 20.0) {
                if ($product->price_lock_baseline === null) {
                    $product->update(['price_lock_baseline' => $costoLocal]);
                }
            }

            // 2. Buscar o crear una AutoOrder abierta para este proveedor
            $autoOrder = AutoOrder::firstOrCreate(
                [
                    'supplier_id' => $supplierId,
                    'status' => AutoOrderStatus::PENDING,
                ],
                [
                    'order_date' => now(),
                    'total_items' => 0,
                    'total_quantity' => 0,
                    'total_amount' => 0,
                ]
            );

            // 3. Añadir o actualizar el detalle
            $detail = AutoOrderDetail::where('order_id', $autoOrder->id)
                ->where('product_id', $productId)
                ->first();

            if ($detail) {
                $detail->quantity += $quantity;
                $detail->unit_cost = $unitCost;
                $detail->subtotal = (float) $detail->quantity * (float) $unitCost;
                $detail->product_suppliers_id = $productSupplierId;
                $detail->save();
            } else {
                $detail = AutoOrderDetail::create([
                    'order_id' => $autoOrder->id,
                    'product_id' => $productId,
                    'product_suppliers_id' => $productSupplierId,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => (float) $quantity * (float) $unitCost,
                ]);
            }

            // 4. Actualizar totales de la orden (esto suele hacerse en un observer o servicio dedicado,
            // pero lo ponemos aquí para asegurar la inmediatez que pide el usuario)
            $this->updateAutoOrderTotals($autoOrder);

            // 5. Limpiar cantidad manual tras el pedido
            $product->update(['manual_solicitar' => null]);

            DB::commit();

            return response()->json([
                'message' => 'Producto añadido a la orden correctamente.',
                'auto_order_id' => $autoOrder->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al añadir a la orden: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Añade múltiples productos a las auto-órdenes de sus respectivos proveedores en una sola transacción.
     */
    public function addMultipleToOrder(AddMultipleToOrderRequest $request): JsonResponse
    {
        $items = $request->items;
        $ordersToUpdate = [];

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                $supplierId = $item['supplier_id'];
                $productSupplierId = $item['product_supplier_id'];
                $customUnitCost = $item['unit_cost'] ?? null;

                $product = Product::findOrFail($productId);
                $ps = ProductSupplier::findOrFail($productSupplierId);

                // Forzar vinculación
                if ($ps && $ps->product_id != $productId) {
                    $ps->update(['product_id' => $productId]);
                }

                $unitCost = $customUnitCost !== null && $customUnitCost > 0
                    ? (float) $customUnitCost
                    : ($ps->unit_cost_usd_with_discount > 0 
                        ? $ps->unit_cost_usd_with_discount 
                        : ($ps->unit_cost_usd > 0 ? $ps->unit_cost_usd : $product->unit_cost));

                // Si el costo de compra tiene un incremento mayor al 20% respecto al costo local, bloquear el precio base
                $costoProveedor = (float)$unitCost;
                $costoLocal = (float)($product->unit_cost ?? 0);
                if ($costoLocal > 0 && ((($costoProveedor - $costoLocal) / $costoLocal) * 100) > 20.0) {
                    if ($product->price_lock_baseline === null) {
                        $product->update(['price_lock_baseline' => $costoLocal]);
                    }
                }

                // Buscar o crear la AutoOrder
                $autoOrder = AutoOrder::firstOrCreate(
                    [
                        'supplier_id' => $supplierId,
                        'status' => AutoOrderStatus::PENDING,
                    ],
                    [
                        'order_date' => now(),
                        'total_items' => 0,
                        'total_quantity' => 0,
                        'total_amount' => 0,
                    ]
                );

                $ordersToUpdate[$autoOrder->id] = $autoOrder;

                // Añadir o actualizar detalle
                $detail = AutoOrderDetail::where('order_id', $autoOrder->id)
                    ->where('product_id', $productId)
                    ->first();

                if ($detail) {
                    $detail->quantity += $quantity;
                    $detail->unit_cost = $unitCost;
                    $detail->subtotal = (float) $detail->quantity * (float) $unitCost;
                    $detail->product_suppliers_id = $productSupplierId;
                    $detail->save();
                } else {
                    $detail = AutoOrderDetail::create([
                        'order_id' => $autoOrder->id,
                        'product_id' => $productId,
                        'product_suppliers_id' => $productSupplierId,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'subtotal' => (float) $quantity * (float) $unitCost,
                    ]);
                }

                $product->update(['manual_solicitar' => null]);
            }

            // Actualizar totales de todas las órdenes modificadas
            foreach ($ordersToUpdate as $order) {
                $this->updateAutoOrderTotals($order);
            }

            DB::commit();

            return response()->json([
                'message' => 'Productos añadidos a las órdenes correctamente.',
                'updated_orders' => array_keys($ordersToUpdate)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al añadir a las órdenes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ignora un producto manualmente por 7 días.
     */
    public function ignore(Request $request, Product $product): JsonResponse
    {
        $product->update(['manual_solicitar' => null]);
        $this->productActionService->ignoreProduct($product, 7);
        return response()->json(['message' => 'Producto ignorado por 7 días y cantidad manual restablecida.']);
    }

    /**
     * Actualiza la cantidad manual sugerida para un producto.
     */
    public function updateManualQuantity(UpdateManualQuantityRequest $request, Product $product): JsonResponse
    {
        $product->update([
            'manual_solicitar' => $request->quantity,
        ]);

        return response()->json([
            'message' => 'Cantidad manual actualizada correctamente.',
            'product_id' => $product->id,
            'manual_solicitar' => $product->manual_solicitar,
        ]);
    }

    /**
     * Actualiza el código de barras de un producto.
     */
    public function updateBarcode(UpdateBarcodeRequest $request, Product $product): JsonResponse
    {
        $barcode = $request->barcode;
        $force = filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN);

        // Buscar si otro producto ya tiene este código de barras (excluyendo eliminados)
        $existingProduct = Product::where('barcode', $barcode)
            ->where('id', '!=', $product->id)
            ->first();

        if ($existingProduct) {
            if ($force) {
                // Si viene forzado, le quitamos el código de barras al otro producto (ponerlo en null)
                $existingProduct->update(['barcode' => null]);
            } else {
                // Si no viene forzado, retornamos conflicto para confirmar en el frontend
                return response()->json([
                    'conflict' => true,
                    'message' => "El código de barras ya pertenece al producto: \"{$existingProduct->name}\" (ID: {$existingProduct->id}). ¿Desea desvincularlo de ese producto y asignarlo a este?",
                    'existing_product' => [
                        'id' => $existingProduct->id,
                        'name' => $existingProduct->name,
                    ]
                ], 409);
            }
        }

        $product->update([
            'barcode' => $barcode,
        ]);

        return response()->json([
            'message' => 'Código de barras actualizado con éxito.',
            'product_id' => $product->id,
            'barcode' => $product->barcode,
        ]);
    }

    /**
     * Restaura todos los productos ignorados para que vuelvan a aparecer en el asistente.
     *
     * @return JsonResponse
     */
    public function clearIgnored(): JsonResponse
    {
        $restoredCount = $this->productActionService->clearIgnoredProducts();

        return response()->json([
            'message' => 'Productos restaurados correctamente.',
            'restored_count' => $restoredCount,
        ]);
    }

    private function updateAutoOrderTotals(AutoOrder $order)
    {
        $details = $order->details()->get();
        $order->update([
            'total_items' => $details->count(),
            'total_quantity' => $details->sum('quantity'),
            'total_amount' => $details->sum(function($detail) {
                return (float) $detail->quantity * (float) $detail->unit_cost;
            }),
        ]);
    }
}
