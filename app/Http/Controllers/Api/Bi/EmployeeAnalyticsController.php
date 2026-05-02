<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\EmployeeAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeAnalyticsController extends Controller
{
    public function __construct(
        protected EmployeeAnalyticsService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
        ];

        $data = $this->service->getFullDashboard($filters);

        return response()->json($data);
    }

    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'employee_a' => 'required|integer',
            'employee_b' => 'required|integer',
        ]);

        $filters = [
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
        ];

        $data = $this->service->getComparison(
            $request->get('employee_a'),
            $request->get('employee_b'),
            $filters
        );

        return response()->json($data);
    }

    public function detail(int $id, Request $request): JsonResponse
    {
        $filters = [
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
        ];

        $data = $this->service->getDetail($id, $filters);

        return response()->json($data);
    }
}
