<?php

namespace App\Repository;

use App\Models\AutoOrderDetail;
use DB;
use Illuminate\Database\Eloquent\Collection;

class AutoOrderDetailsRepository
{
    public function create(array $datos): ?AutoOrderDetail
    {
        $record = AutoOrderDetail::create($datos);
        return $record;
    }

    public function getPurchaseOrderDetails($filters)
    {
        $orderId = $filters["id"];
        $order = \App\Models\AutoOrder::find($orderId);

        // La sincronización automática aplica a órdenes en estado enviadas (status = 1)
        if ($order && $order->status->value === 1) {
            $supplierId = $order->supplier_id;

            // Buscar productos en facturas cargadas o aprobadas del mismo proveedor
            $receivedProductIds = \App\Models\InvoiceDetail::whereHas('invoice', function ($query) use ($supplierId) {
                $query->where('supplier_id', $supplierId)
                    ->whereIn('status', ['loaded', 'to_order', 'ordered']);
            })->pluck('product_id')->unique()->toArray();

            if (!empty($receivedProductIds)) {
                // Obtener los detalles de la orden que aún están pendientes
                $pendingOrderDetails = AutoOrderDetail::where("order_id", $orderId)
                    ->whereNull('received')
                    ->with(['productSupplier'])
                    ->get();

                $hasUpdates = false;
                foreach ($pendingOrderDetails as $detail) {
                    $productId = $detail->productSupplier->product_id ?? null;
                    
                    // Si el producto está presente en alguna de las facturas cargadas del proveedor
                    if ($productId && in_array($productId, $receivedProductIds)) {
                        $detail->update([
                            'received' => 1,
                            'status'   => \App\AutoOrderDetailStatus::ARRIVED->value
                        ]);
                        $hasUpdates = true;
                    }
                }

                if ($hasUpdates) {
                    // Sincronizar el estado de la orden principal (completada)
                    $repo = new AutoOrdersRepository();
                    $repo->checkAndCompleteOrder($order);
                }
            }
        }

        $results = AutoOrderDetail::where("order_id", $orderId)
            ->with(["productSupplier"])
            ->orderBy("subtotal", "desc")
            ->paginate(10)
            ->through(fn($record) => [...$record->toArray(), "product_name" => $record->productSupplier->name ?? null]);

        return $results;
    }

    public function deleteDetail(AutoOrderDetail $autoOrderDetail)
    {
        $autoOrder = $autoOrderDetail->order;
        $autoOrder->decrement("total_quantity", $autoOrderDetail->quantity);
        $autoOrder->decrement("total_items");
        $autoOrder->decrement("total_amount", $autoOrderDetail->subtotal);
        $autoOrderDetail->delete();
        return response()->json(["message" => "Eliminado"]);
    }

    public function orderHistory(array $data)
    {
        $id = $data["id"];
        $perPage = $data["perPage"] ?? 10;

        $results = AutoOrderDetail::query()
            ->select(["auto_order_details.*", "products.name as product_name"])
            ->leftJoin("product_suppliers", "product_suppliers.id", "=", "auto_order_details.product_suppliers_id")
            ->leftJoin("products", "products.id", "=", "product_suppliers.product_id")
            ->where("auto_order_details.order_id", $id)
            ->orderBy("auto_order_details.subtotal", "desc")
            ->paginate($perPage);

        return $results;
    }

    public function consultDetailByProductSupplierId($product_supplier_id): int|null
    {
        return AutoOrderDetail::where("product_suppliers_id", $product_supplier_id)
            ->where("status", 0)
            ->whereHas('order', function ($query) {
                $query->whereIn('status', [0, 1]);
            })
            ->sum("quantity");
    }

    public function updateDetailStatus($autoOrderDetail, $data): bool
    {
        try {
            DB::beginTransaction();
            
            $status = $data['status'] ? \App\AutoOrderDetailStatus::ARRIVED : \App\AutoOrderDetailStatus::NOT_ARRIVED;
            
            $autoOrderDetail->update([
                'received' => $data['status'],
                'status'   => $status->value
            ]);

            // Disparar verificación de completado de la orden
            $repo = new AutoOrdersRepository();
            $repo->checkAndCompleteOrder($autoOrderDetail->order);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }
}
