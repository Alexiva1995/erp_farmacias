<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAutoOrderDetailsRequest;
use App\Models\AutoOrder;
use App\Repository\AutoOrdersRepository;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(protected AutoOrdersRepository $autoOrdersRepository)
    {
    }

    public function getPurchaseOrders(Request $request)
    {
        $filters = $request->query();
        $paginated = $this->autoOrdersRepository->getAll($filters);

        return response()->json([
            "data" => $paginated->items(),
            "total" => $paginated->total(),
        ]);
    }

    public function destroy(AutoOrder $autoOrder)
    {
        return $this->autoOrdersRepository->delete($autoOrder);
    }

    public function updateDetails(AutoOrder $autoOrder, UpdateAutoOrderDetailsRequest $request)
    {
        $result = $this->autoOrdersRepository->update($autoOrder, $request->all());

        return response()->json($result);
    }

    public function getPurchaseOrderHistory(Request $request)
    {
        $filters = $request->query();
        $paginated = $this->autoOrdersRepository->getHistory($filters);

        return response()->json([
            "data" => $paginated->items(),
            "total" => $paginated->total(),
        ]);
    }

    public function getExportData(AutoOrder $autoOrder)
    {
        $data = $this->autoOrdersRepository->getExportableData($autoOrder);

        return response()->json(["data" => $data]);
    }
}
