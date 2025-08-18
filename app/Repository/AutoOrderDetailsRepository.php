<?php

namespace App\Repository;

use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use Illuminate\Support\Facades\DB;

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
}
