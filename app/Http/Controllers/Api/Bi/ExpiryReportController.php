<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\ExpiryReportRequest;
use App\Http\Resources\Bi\ExpiryReportResource;
use App\Services\Bi\ExpiryReportService;

class ExpiryReportController extends Controller
{
    protected $service;

    public function __construct(ExpiryReportService $service)
    {
        $this->service = $service;
    }

    public function index(ExpiryReportRequest $request): ExpiryReportResource
    {
        $data = $this->service->getDashboardData($request->validated());

        return new ExpiryReportResource($data);
    }
}
