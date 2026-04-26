<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\ProductMasterReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductMasterReportController extends Controller
{
    protected $service;

    public function __construct(ProductMasterReportService $service)
    {
        $this->service = $service;
    }

    public function getDashboard(Request $request): JsonResponse
    {
        $filters = $request->only(['start_date', 'end_date', 'laboratory_id', 'group_id']);
        $data = $this->service->getDashboardData($filters);
        
        return response()->json($data);
    }

    public function getTrends(Request $request): JsonResponse
    {
        $filters = $request->only(['product_id', 'group_id', 'start_date', 'end_date']);
        $data = $this->service->getTrendData($filters);
        
        return response()->json($data);
    }
}
