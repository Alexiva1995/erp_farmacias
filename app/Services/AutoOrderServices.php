<?php

namespace App\Services;

use App\Contracts\AutoOrder;
use App\Models\AutoOrder as ModelsAutoOrder;
use App\Models\Supplier;
use App\Repository\AutoOrderDetailsRepository;
use App\Repository\AutoOrdersRepository;
use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;

class AutoOrderServices implements AutoOrder
{
    public function __construct(
        protected AutoOrdersRepository $autoOrdersRepository,
        protected AutoOrderDetailsRepository $autoOrderDetailsRepository,
    ) {}

    public function create(array $order): ModelsAutoOrder
    {
        $today = new DateTime("now");
        $order["order_date"] = $today->format("Y-m-d");

        $existingOrder = ModelsAutoOrder::where('supplier_id', $order['supplier_id'])
            ->where('status', \App\Enums\AutoOrderStatus::PENDING)
            ->orderByDesc("created_at")
            ->first();

        if ($existingOrder) {
            foreach ($order["details"] as $detail) {
                $productId = $detail["product_suppliers_id"];
                $quantity = $detail["quantity"];
                $subtotal = $detail["subtotal"] ?? ($quantity * $detail["unit_cost"]);

                $existingDetail = $existingOrder->details()->where('product_suppliers_id', $productId)->first();
                
                if ($existingDetail) {
                    $existingDetail->increment('quantity', $quantity);
                    $existingDetail->increment('subtotal', $subtotal);
                } else {
                    $detail["order_id"] = $existingOrder->id;
                    $this->autoOrderDetailsRepository->create($detail);
                    $existingOrder->increment("total_items", 1);
                }
                
                $existingOrder->increment("total_quantity", $quantity);
                $existingOrder->increment("total_amount", $subtotal);
            }
            $existingOrder->details;
            return $existingOrder;
        }

        $autoOrder = $this->autoOrdersRepository->create($order);
        foreach ($order["details"] as $key => $detail) {
            # code...
            $detail["order_id"] = $autoOrder->id;
            // $detail["final_cost"] = 0;
            $autoOrderDetail = $this->autoOrderDetailsRepository->create($detail);
        }
        $autoOrder->details;
        return $autoOrder;
    }

    public function createMultiple(array $orders, array $withoutSupplierIds = []): array
    {
        /*$listAutoOrders = [];

        foreach ($orders as $key => $order) {
            $listAutoOrders[] = $this->create($order);
        }

        return $listAutoOrders;*/
        return DB::transaction(function () use ($orders, $withoutSupplierIds) {
        
        $listAutoOrders = [];

        // 1. Crear las órdenes normales
        foreach ($orders as $order) {
            $listAutoOrders[] = $this->create($order);
        }

        // 2. MARCAR SOLO LOS QUE NO TIENEN PROVEEDOR
        if (!empty($withoutSupplierIds)) {
            Product::whereIn('id', $withoutSupplierIds)
                ->update(['is_ordered' => true]);
        }

        return $listAutoOrders;
        });
    }


    public function getMarkedProductsWithoutSupplier(int $perPage = 10, string $sortBy = 'id', string $order = 'desc')
    {
        return Product::select([
            'products.*', 
            // Subconsulta para sumar la cantidad total en todos los lotes del producto
            DB::raw('(SELECT COALESCE(SUM(quantity), 0) 
                      FROM product_lots 
                      WHERE product_id = products.id) AS lote_quantity')
        ])
        ->where('is_ordered', true)
        ->with('laboratory:id,name')
        ->orderBy($sortBy, $order)
        ->paginate($perPage);
    }
}
