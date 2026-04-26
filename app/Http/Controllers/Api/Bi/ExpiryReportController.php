<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\ExpiryReportRequest;
use App\Services\Bi\ExpiryReportService;
use Illuminate\Http\JsonResponse;

class ExpiryReportController extends Controller
{
    protected $service;

    public function __construct(ExpiryReportService $service)
    {
        $this->service = $service;
    }

    public function index(ExpiryReportRequest $request): JsonResponse
    {
        $data = $this->service->getDashboardData($request->validated());

        return response()->json($data);
    }
}
