<?php

namespace App\Http\Controllers;

use App\Contracts\PurchaseOrder;
use App\Helpers\ApiResponse;
use App\Http\Requests\GetPurchaseOrdersRequest;
use App\Http\Requests\UpdateAutoOrderDetailsRequest;
use App\Http\Resources\AutoOrderResource;
use App\Models\AutoOrder;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrder $purchaseOrder)
    {
    }

    public function getPurchaseOrders(GetPurchaseOrdersRequest $request)
    {
        $filters = $request->validated();
        $paginated = $this->purchaseOrder->getAll($filters);

        return ApiResponse::success([
            "data" => AutoOrderResource::collection($paginated->items()),
            "total" => $paginated->total(),
        ]);
    }

    public function getStats(GetPurchaseOrdersRequest $request)
    {
        $filters = $request->validated();
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
        $data = $request->validated();
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
            "data" => AutoOrderResource::collection($paginated->items()),
            "total" => $paginated->total(),
        ]);
    }

    public function getExportData(AutoOrder $autoOrder)
    {
        $data = $this->purchaseOrder->getExportableData($autoOrder);

        return ApiResponse::success(["data" => $data]);
    }

    public function confirmSent(AutoOrder $autoOrder)
    {
        return response()->json($this->purchaseOrder->confirmSent($autoOrder));
    }

    public function finish(AutoOrder $autoOrder)
    {
        return response()->json($this->purchaseOrder->finish($autoOrder));
    }

    public function rejectPendingDetails(AutoOrder $autoOrder)
    {
        $this->purchaseOrder->rejectPendingDetails($autoOrder);
        return response()->json(['status' => 'ok', 'message' => 'Productos pendientes marcados como rechazados.']);
    }

    public function revertToSent(AutoOrder $autoOrder)
    {
        return response()->json(['success' => $this->purchaseOrder->revertToSent($autoOrder)]);
    }

    public function resendFtp(AutoOrder $autoOrder)
    {
        try {
            $this->purchaseOrder->confirmSent($autoOrder);
            return response()->json([
                'success' => true,
                'message' => 'Pedido reenviado exitosamente por FTP.'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
