<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\BenchmarkingRequest;
use App\Http\Requests\Bi\LaboratoryDeepDiveRequest;
use App\Http\Requests\Bi\LaboratoryReportRequest;
use App\Http\Resources\Bi\LaboratoryDeepDiveResource;
use App\Http\Resources\Bi\LaboratoryRankingResource;
use App\Services\Bi\LaboratoryMasterReportService;
use Illuminate\Http\JsonResponse;

class LaboratoryMasterReportController extends Controller
{
    public function __construct(
        protected LaboratoryMasterReportService $service
    ) {}

    public function index(LaboratoryReportRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $data = $this->service->getDashboardSummary($filters);

        return response()->json([
            'rankings' => [
                'by_units' => [
                    'data' => LaboratoryRankingResource::collection($data['rankings']['by_units']->items()),
                    'total' => $data['rankings']['by_units']->total(),
                    'current_page' => $data['rankings']['by_units']->currentPage(),
                    'per_page' => $data['rankings']['by_units']->perPage(),
                ],
                'by_revenue' => [
                    'data' => LaboratoryRankingResource::collection($data['rankings']['by_revenue']->items()),
                    'total' => $data['rankings']['by_revenue']->total(),
                    'current_page' => $data['rankings']['by_revenue']->currentPage(),
                    'per_page' => $data['rankings']['by_revenue']->perPage(),
                ],
                'by_stock' => [
                    'data' => LaboratoryRankingResource::collection($data['rankings']['by_stock']->items()),
                    'total' => $data['rankings']['by_stock']->total(),
                    'current_page' => $data['rankings']['by_stock']->currentPage(),
                    'per_page' => $data['rankings']['by_stock']->perPage(),
                ],
            ],
            'trends' => $data['trends'],
            'stock_on_hand' => $data['stock_on_hand'],
            'profitability' => $data['profitability'],
        ]);
    }

    public function getRankings(LaboratoryReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $metric = $validated['metric'] ?? 'total_units';
        $page = (int) ($validated['page'] ?? 1);

        $paginated = $this->service->getRankings($metric, $page, $validated);

        return response()->json([
            'data' => LaboratoryRankingResource::collection($paginated->items()),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    public function getDeepDive(int $id, LaboratoryDeepDiveRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $data = $this->service->getLaboratoryDeepDive($id, $filters);

        return response()->json(new LaboratoryDeepDiveResource($data));
    }

    public function getBenchmarking(BenchmarkingRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $data = $this->service->getBenchmarking(
            (int) $filters['lab_a'],
            (int) $filters['lab_b'],
            $filters
        );

        return response()->json($data);
    }

    public function getFilterCatalogs(LaboratoryReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $groupByCorporate = (bool) ($validated['group_by_corporate'] ?? false);

        $catalogs = $this->service->getCatalogs($groupByCorporate);

        return response()->json($catalogs);
    }
}
