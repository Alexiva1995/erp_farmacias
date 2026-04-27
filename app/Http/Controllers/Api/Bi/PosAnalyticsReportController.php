<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\PosAnalyticsReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PosAnalyticsReportController extends Controller
{
    public function __construct(
        protected PosAnalyticsReportService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->all();
        $data = $this->service->getPosDashboard($filters);
        
        return response()->json($data);
    }
}
