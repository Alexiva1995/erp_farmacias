<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\PosAnalyticsReportService;
use App\Http\Requests\Bi\PosAnalyticsReportRequest;
use Illuminate\Http\JsonResponse;

class PosAnalyticsReportController extends Controller
{
    public function __construct(
        protected PosAnalyticsReportService $service
    ) {}

    public function index(PosAnalyticsReportRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $data = $this->service->getPosDashboard($filters);
        
        return response()->json($data);
    }
}
