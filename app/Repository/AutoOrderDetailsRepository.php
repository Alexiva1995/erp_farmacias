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
        $results = AutoOrderDetail::where("order_id", $filters["id"])
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
        return AutoOrderDetail::where("product_suppliers_id", $product_supplier_id)->where("status", 0)->sum("quantity");
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
