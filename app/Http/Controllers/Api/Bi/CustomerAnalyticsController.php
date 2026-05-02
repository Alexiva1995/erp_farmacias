<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\CustomerAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerAnalyticsController extends Controller
{
    public function __construct(
        protected CustomerAnalyticsService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
        ];

        $data = $this->service->getDashboardData($filters);

        return response()->json($data);
    }
}
