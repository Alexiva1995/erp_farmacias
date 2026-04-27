<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\InventoryCyclicReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryCyclicReportController extends Controller
{
    public function __construct(
        protected InventoryCyclicReportService $service
    ) {}

    /**
     * Obtiene los datos del dashboard de inventario cíclico
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['start_date', 'end_date']);
        
        $data = $this->service->getInventoryDashboard($filters);
        
        return response()->json($data);
    }
}
