<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
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
    public function addToOrder(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'product_supplier_id' => 'nullable|exists:product_suppliers,id',
        ]);

        $productId = $request->product_id;
        $quantity = $request->quantity;
        $supplierId = $request->supplier_id;
        $productSupplierId = $request->product_supplier_id;

        DB::beginTransaction();
        try {
            $product = Product::findOrFail($productId);

            // 1. Si no hay product_supplier_id pero sí hay supplierId, intentar encontrar o crear el enlace
            if (!$productSupplierId && $supplierId) {
                $ps = ProductSupplier::where('product_id', $productId)
                    ->where('supplier_id', $supplierId)
                    ->first();
                
                if (!$ps) {
                    // Crear enlace básico si no existe (esto sucede en el modal de comparación manual)
                    $ps = ProductSupplier::create([
                        'product_id' => $productId,
                        'supplier_id' => $supplierId,
                        'unit_cost_usd' => $product->unit_cost, // Valor base
                    ]);
                }
                $productSupplierId = $ps->id;
            }

            if (!$supplierId && $productSupplierId) {
                $supplierId = ProductSupplier::find($productSupplierId)->supplier_id;
            }

            if (!$supplierId) {
                return response()->json(['message' => 'No se pudo determinar el proveedor para este producto.'], 422);
            }

            // 2. Buscar o crear una AutoOrder abierta para este proveedor
            $autoOrder = AutoOrder::firstOrCreate(
                [
                    'supplier_id' => $supplierId,
                    'status' => AutoOrderStatus::Open,
                ],
                [
                    'order_date' => now(),
                    'total_items' => 0,
                    'total_quantity' => 0,
                    'total_amount' => 0,
                ]
            );

            // 3. Añadir o actualizar el detalle
            $detail = AutoOrderDetail::updateOrCreate(
                [
                    'order_id' => $autoOrder->id,
                    'product_id' => $productId,
                ],
                [
                    'product_suppliers_id' => $productSupplierId,
                    'quantity' => DB::raw("quantity + $quantity"),
                ]
            );

            // 4. Actualizar totales de la orden (esto suele hacerse en un observer o servicio dedicado,
            // pero lo ponemos aquí para asegurar la inmediatez que pide el usuario)
            $this->updateAutoOrderTotals($autoOrder);

            // 5. Ignorar el producto por 7 días tras el pedido
            $this->productActionService->ignoreProduct($product, 7);

            DB::commit();

            return response()->json([
                'message' => 'Producto añadido a la orden y ocultado por 7 días.',
                'auto_order_id' => $autoOrder->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al añadir a la orden: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ignora un producto manualmente por 7 días.
     */
    public function ignore(Request $request, Product $product): JsonResponse
    {
        $this->productActionService->ignoreProduct($product, 7);
        return response()->json(['message' => 'Producto ignorado por 7 días.']);
    }

    private function updateAutoOrderTotals(AutoOrder $order)
    {
        $details = $order->details()->get();
        $order->update([
            'total_items' => $details->count(),
            'total_quantity' => $details->sum('quantity'),
            // El cálculo del total_amount depende de si los precios están en los detalles
            // Por ahora mantenemos los ítems y cantidades.
        ]);
    }
}
