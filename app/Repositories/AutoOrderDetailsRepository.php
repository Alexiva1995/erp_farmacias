<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AutoOrderDetail;
use DB;
use Illuminate\Database\Eloquent\Collection;

class AutoOrderDetailsRepository
{
    public function create(array $datos): ?AutoOrderDetail
    {
        // 1. Resolver producto a partir de product_suppliers si está presente
        if (!empty($datos['product_suppliers_id']) && empty($datos['product_id'])) {
            $ps = \App\Models\ProductSupplier::find($datos['product_suppliers_id']);
            if ($ps && $ps->product_id) {
                $datos['product_id'] = $ps->product_id;
            } elseif ($ps && !empty($ps->barcode)) {
                $masterClient = app(\App\Services\Catalog\MasterCatalogClientService::class);
                $product = $masterClient->ensureLocalProductFromBarcode($ps->barcode);
                if ($product) {
                    $datos['product_id'] = $product->id;
                    $ps->update(['product_id' => $product->id]);
                }
            }
        }

        // 2. Si se pasó directamente un barcode y no hay product_id
        if (empty($datos['product_id']) && !empty($datos['barcode'])) {
            $masterClient = app(\App\Services\Catalog\MasterCatalogClientService::class);
            $product = $masterClient->ensureLocalProductFromBarcode($datos['barcode']);
            if ($product) {
                $datos['product_id'] = $product->id;
            }
        }

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

            // Buscar productos en facturas cargadas o aprobadas del mismo proveedor registradas después o asociadas a la orden de compra
            $receivedProductIds = \App\Models\InvoiceDetail::whereHas('invoice', function ($query) use ($supplierId, $order) {
                $query->where('supplier_id', $supplierId)
                    ->whereIn('status', ['loaded', 'to_order', 'ordered'])
                    ->where(function ($q) use ($order) {
                        $q->where('created_at', '>=', $order->created_at)
                          ->orWhere('auto_order_id', $order->id);
                    });
            })->pluck('product_id')->unique()->toArray();

            if (!empty($receivedProductIds)) {
                // Obtener los detalles de la orden que aún están pendientes
                $pendingOrderDetails = AutoOrderDetail::where("order_id", $orderId)
                    ->whereNull('received')
                    ->with(['productSupplier'])
                    ->get();

                $hasUpdates = false;
                foreach ($pendingOrderDetails as $detail) {
                    $productId = $detail->product_id ?? $detail->productSupplier?->product_id;
                    
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

        $perPage = isset($filters["perPage"]) ? min(max((int)$filters["perPage"], 1), 100) : 10;
        
        $query = AutoOrderDetail::query()
            ->select(["auto_order_details.*", DB::raw("COALESCE(product_suppliers.name, products.name) as product_name")])
            ->leftJoin("product_suppliers", "product_suppliers.id", "=", "auto_order_details.product_suppliers_id")
            ->leftJoin("products", "products.id", "=", "auto_order_details.product_id")
            ->where("auto_order_details.order_id", $orderId);

        if (!empty($filters["search"])) {
            $query->where(function($q) use ($filters) {
                $q->where("products.name", "like", "%" . $filters["search"] . "%")
                  ->orWhere("product_suppliers.name", "like", "%" . $filters["search"] . "%");
            });
        }

        $results = $query->orderBy("product_name", "asc")
            ->paginate($perPage);

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
            ->select(["auto_order_details.*", DB::raw("COALESCE(product_suppliers.name, products.name) as product_name")])
            ->leftJoin("product_suppliers", "product_suppliers.id", "=", "auto_order_details.product_suppliers_id")
            ->leftJoin("products", "products.id", "=", "auto_order_details.product_id")
            ->where("auto_order_details.order_id", $id)
            ->orderBy("auto_order_details.subtotal", "desc")
            ->paginate($perPage);

        return $results;
    }

    public function consultDetailByProductSupplierId($product_supplier_id): int|null
    {
        $sum = AutoOrderDetail::where("product_suppliers_id", $product_supplier_id)
            ->where("status", 0)
            ->whereHas('order', function ($query) {
                $query->whereIn('status', [0, 1]);
            })
            ->sum("quantity");

        return $sum !== null ? (int)$sum : null;
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
