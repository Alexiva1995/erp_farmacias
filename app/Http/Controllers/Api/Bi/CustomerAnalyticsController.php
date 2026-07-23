<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\CustomerAnalyticsRequest;
use App\Http\Resources\Bi\CustomerAnalyticsResource;
use App\Services\Bi\CustomerAnalyticsService;

class CustomerAnalyticsController extends Controller
{
    public function __construct(
        protected CustomerAnalyticsService $service
    ) {}

    public function index(CustomerAnalyticsRequest $request): CustomerAnalyticsResource
    {
        $filters = $request->validated();

        $data = $this->service->getDashboardData($filters);

        return new CustomerAnalyticsResource($data);
    }
}
