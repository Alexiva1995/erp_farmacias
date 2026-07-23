<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\LaboratoryMasterReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Bi\BenchmarkingRequest;

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

    public function getBenchmarking(BenchmarkingRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $data = $this->service->getBenchmarking(
            (int)$filters['lab_a'], 
            (int)$filters['lab_b'], 
            $filters
        );
        
        return response()->json($data);
    }

    public function getFilterCatalogs(Request $request): JsonResponse
    {
        $groupByCorporate = filter_var($request->get('group_by_corporate', false), FILTER_VALIDATE_BOOLEAN);
        
        if ($groupByCorporate) {
            $data = \Illuminate\Support\Facades\DB::table('groups_laboratories')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        } else {
            $data = \Illuminate\Support\Facades\DB::table('laboratories')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }
        
        return response()->json($data);
    }
}
