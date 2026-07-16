<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Enums\AutoOrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierOrderResponseController extends Controller
{
    /**
     * Muestra la orden de compra pública a partir de su hash_token.
     */
    public function show(string $hash): JsonResponse
    {
        $order = AutoOrder::where('hash_token', $hash)
            ->with(['supplier:id,name,rif,email,phone', 'details' => function ($q) {
                // Seleccionar solo detalles activos
                $q->whereNull('deleted_at');
            }, 'details.productSupplier:id,name,cod_supplier,barcode_match'])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Orden de compra no encontrada o enlace inválido.'], 404);
        }

        // Traducir estructura de ítems para el frontend
        $items = $order->details->map(fn($detail) => [
            'id'                       => $detail->id,
            'product_id'               => $detail->product_id,
            'name'                     => $detail->productSupplier?->name ?? 'Producto no especificado',
            'cod_supplier'             => $detail->productSupplier?->cod_supplier ?? '—',
            'quantity'                 => $detail->quantity,
            'unit_cost'                => $detail->unit_cost,
            'subtotal'                 => $detail->subtotal,
            'supplier_confirmed'       => $detail->supplier_confirmed,
            'supplier_rejected_reason' => $detail->supplier_rejected_reason,
        ]);

        return response()->json([
            'id'             => $order->id,
            'order_date'     => $order->order_date,
            'total_items'    => $order->total_items,
            'total_quantity' => $order->total_quantity,
            'total_amount'   => $order->total_amount,
            'status'         => $order->status, // 0 = PENDING, 1 = SENT, etc.
            'supplier'       => $order->supplier,
            'items'          => $items,
        ]);
    }

    /**
     * Procesa la respuesta del proveedor (aprobar / rechazar ítems).
     */
    public function respond(Request $request, string $hash): JsonResponse
    {
        $order = AutoOrder::where('hash_token', $hash)->first();

        if (!$order) {
            return response()->json(['message' => 'Orden de compra no encontrada.'], 404);
        }

        $request->validate([
            'items'                             => 'required|array',
            'items.*.id'                        => 'required|integer|exists:auto_order_details,id',
            'items.*.supplier_confirmed'        => 'required|boolean',
            'items.*.supplier_rejected_reason'  => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->items as $itemData) {
                $detail = AutoOrderDetail::where('order_id', $order->id)
                    ->where('id', $itemData['id'])
                    ->first();

                if ($detail) {
                    $detail->update([
                        'supplier_confirmed'       => $itemData['supplier_confirmed'],
                        'supplier_rejected_reason' => $itemData['supplier_confirmed'] ? null : ($itemData['supplier_rejected_reason'] ?? 'Sin stock'),
                    ]);
                }
            }

            // Recalcular totales de la orden de compra restando los productos rechazados del total_amount
            $this->recalcularTotalesOrden($order);

            DB::commit();

            return response()->json([
                'message' => 'Respuesta registrada correctamente. Muchas gracias.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar respuesta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Recalcula los totales de la orden sumando únicamente los productos aceptados por el proveedor.
     */
    private function recalcularTotalesOrden(AutoOrder $order): void
    {
        // Detalles activos que NO han sido rechazados por el proveedor
        $totals = AutoOrderDetail::where('order_id', $order->id)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('supplier_confirmed', true)
                  ->orWhereNull('supplier_confirmed'); // si no han respondido aún
            })
            ->selectRaw('COUNT(*) as total_items, SUM(quantity) as total_quantity, SUM(subtotal) as total_amount')
            ->first();

        $order->update([
            'total_items'    => $totals->total_items    ?? 0,
            'total_quantity' => $totals->total_quantity ?? 0,
            'total_amount'   => $totals->total_amount   ?? 0,
        ]);
    }
}
