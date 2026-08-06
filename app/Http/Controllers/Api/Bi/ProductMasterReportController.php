<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\ProductReportDashboardRequest;
use App\Http\Requests\Bi\ProductReportRankingsRequest;
use App\Services\Bi\ProductMasterReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductMasterReportController extends Controller
{
    public function __construct(
        protected readonly ProductMasterReportService $service
    ) {}

    public function getDashboard(ProductReportDashboardRequest $request): JsonResponse
    {
        $data = $this->service->getDashboardData($request->validated());

        return response()->json($data);
    }

    public function getTrends(ProductReportDashboardRequest $request): JsonResponse
    {
        $filters = $request->validated();
        // Acepta también product_id y group_id para filtro de tendencias
        $filters['product_id'] = $request->query('product_id');
        $filters['group_id']   = $request->query('group_id');

        $data = $this->service->getTrendData($filters);

        return response()->json($data);
    }

    public function getCrossSelling(ProductReportDashboardRequest $request): JsonResponse
    {
        $filters         = $request->validated();
        $filters['page'] = max(1, (int) $request->query('page', 1));

        $data = $this->service->getCrossSellingData($filters);

        return response()->json($data);
    }

    public function getRankings(ProductReportRankingsRequest $request): JsonResponse
    {
        $data = $this->service->getRankingsData($request->validated());

        return response()->json($data);
    }
}
