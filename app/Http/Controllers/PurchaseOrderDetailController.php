<?php

namespace App\Http\Controllers;

use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Repositories\AutoOrderDetailsRepository;
use Illuminate\Http\Request;

class PurchaseOrderDetailController extends Controller
{
    public function __construct(protected AutoOrderDetailsRepository $autoOrderDetailsRepository)
    {
    }

    public function getPurchaseOrderDetails(AutoOrder $autoOrder, Request $request)
    {
        $filters = $request->query();
        $filters["id"] = $autoOrder->id;
        $paginated = $this->autoOrderDetailsRepository->getPurchaseOrderDetails($filters);

        return response()->json([
            "data" => $paginated->items(),
            "total" => $paginated->total(),
        ]);
    }

    public function destroy(AutoOrderDetail $autoOrderDetail)
    {
        return $this->autoOrderDetailsRepository->deleteDetail($autoOrderDetail);
    }

    public function getPurchaseOrderDetailsHistory(AutoOrder $autoOrder, Request $request)
    {
        $filters = $request->query();
        $filters["id"] = $autoOrder->id;
        $paginated = $this->autoOrderDetailsRepository->orderHistory($filters);

        return response()->json([
            "data" => $paginated->items(),
            "total" => $paginated->total(),
        ]);
    }

    public function updateDetailStatus(AutoOrderDetail $autoOrderDetail, Request $request)
    {
        $data = ['status' => $request->boolean('status', FILTER_VALIDATE_BOOL)];
        $response = $this->autoOrderDetailsRepository->updateDetailStatus($autoOrderDetail, $data);

        return response()->json([
            "status" => $response,
            "message" => $response ? 'Estado actualizado' : 'No se pudo actualizar el estado',
        ]);
    }
}
