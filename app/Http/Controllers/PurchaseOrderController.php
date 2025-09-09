<?php

namespace App\Http\Controllers;

use App\Contracts\PurchaseOrder;
use App\Http\Requests\UpdateAutoOrderDetailsRequest;
use App\Models\AutoOrder;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrder $purchaseOrder)
    {
    }

    public function getPurchaseOrders(Request $request)
    {
        $filters = $request->query();
        $paginated = $this->purchaseOrder->getAll($filters);

        return response()->json([
            "data" => $paginated->items(),
            "total" => $paginated->total(),
        ]);
    }

    public function destroy(AutoOrder $autoOrder)
    {
        return $this->purchaseOrder->delete($autoOrder);
    }

    public function updateDetails(AutoOrder $autoOrder, UpdateAutoOrderDetailsRequest $request)
    {
        $result = $this->purchaseOrder->update($autoOrder, $request->all());

        return response()->json($result);
    }

    public function getPurchaseOrderHistory(Request $request)
    {
        $filters = $request->query();
        $paginated = $this->purchaseOrder->getHistory($filters);

        return response()->json([
            "data" => $paginated->items(),
            "total" => $paginated->total(),
        ]);
    }

    public function getExportData(AutoOrder $autoOrder)
    {
        $data = $this->purchaseOrder->getExportableData($autoOrder);

        return response()->json(["data" => $data]);
    }
}
