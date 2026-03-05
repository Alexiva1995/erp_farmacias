<?php

namespace App\Http\Controllers;

use App\Contracts\PurchaseOrder;
use App\Helpers\ApiResponse;
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

        return ApiResponse::success([
            "data" => $paginated->items(),
            "total" => $paginated->total(),
        ]);
    }

    public function getStats(Request $request)
    {
        $filters = $request->query();
        $stats = $this->purchaseOrder->getStats($filters);

        return ApiResponse::success($stats);
    }

    public function destroy(AutoOrder $autoOrder)
    {
        $result = $this->purchaseOrder->delete($autoOrder);

        return $result
            ? ApiResponse::success(['status' => 'ok'])
            : ApiResponse::error(['status' => 'error']);
    }

    public function updateDetails(AutoOrder $autoOrder, UpdateAutoOrderDetailsRequest $request)
    {
        $data = $request->all();
        if (empty($data)) {
            return ApiResponse::error(["status" => "error"], 'No hay datos proporcionados', 200);
        }

        $result = $this->purchaseOrder->update($autoOrder, $data);

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

        return ApiResponse::success(["data" => $data]);
    }
}
