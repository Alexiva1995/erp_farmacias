<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\InventoryCyclicReportRequest;
use App\Http\Resources\Bi\InventoryCyclicReportResource;
use App\Services\Bi\InventoryCyclicReportService;
use Illuminate\Http\JsonResponse;

class InventoryCyclicReportController extends Controller
{
    public function __construct(
        protected InventoryCyclicReportService $service
    ) {}

    /**
     * Obtiene los datos del dashboard de inventario cíclico
     */
    public function index(InventoryCyclicReportRequest $request): InventoryCyclicReportResource
    {
        $filters = $request->validated();
        
        $data = $this->service->getInventoryDashboard($filters);
        
        return new InventoryCyclicReportResource($data);
    }
}
