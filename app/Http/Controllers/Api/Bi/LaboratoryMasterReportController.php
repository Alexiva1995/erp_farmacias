<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\LaboratoryMasterReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LaboratoryMasterReportController extends Controller
{
    public function __construct(
        protected LaboratoryMasterReportService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->all();
        $data = $this->service->getDashboardSummary($filters);
        
        return response()->json($data);
    }

    public function getRankings(Request $request): JsonResponse
    {
        $metric = $request->get('metric', 'total_units');
        $page = $request->get('page', 1);
        $filters = $request->all();
        
        $data = $this->service->getRankings($metric, $page, $filters);
        
        return response()->json($data);
    }

    public function getDeepDive(int $id, Request $request): JsonResponse
    {
        $filters = $request->all();
        $data = $this->service->getLaboratoryDeepDive($id, $filters);
        
        return response()->json($data);
    }

    public function getBenchmarking(Request $request): JsonResponse
    {
        $request->validate([
            'lab_a' => 'required|integer',
            'lab_b' => 'required|integer',
        ]);

        $filters = $request->all();
        $data = $this->service->getBenchmarking(
            (int)$request->lab_a, 
            (int)$request->lab_b, 
            $filters
        );
        
        return response()->json($data);
    }
}
