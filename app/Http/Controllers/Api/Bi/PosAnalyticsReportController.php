<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\PosAnalyticsReportService;
use App\Http\Requests\Bi\PosAnalyticsReportRequest;
use App\Http\Resources\Bi\PosAnalyticsResource;

class PosAnalyticsReportController extends Controller
{
    public function __construct(
        protected PosAnalyticsReportService $service
    ) {}

    public function index(PosAnalyticsReportRequest $request): PosAnalyticsResource
    {
        $filters = $request->validated();
        $data = $this->service->getPosDashboard($filters);
        
        return new PosAnalyticsResource($data);
    }
}
