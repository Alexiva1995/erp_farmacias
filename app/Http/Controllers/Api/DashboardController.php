<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Islr\IslrQueryService;
use App\Services\Order\OrderQueryService;
use App\Services\Dashboard\DashboardQueryService;
use App\Http\Requests\Dashboard\DashboardStatsRequest;
use App\Http\Requests\Dashboard\PopularProductsRequest;
use App\Http\Resources\Dashboard\UnitsSoldResource;
use App\Http\Resources\Dashboard\ProfitResource;
use App\Http\Resources\Dashboard\PopularProductResource;
use App\Http\Resources\Dashboard\SoldExpiringProductResource;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct(
        private IslrQueryService $islrQueryService,
        private OrderQueryService $orderQueryService,
        private DashboardQueryService $dashboardQueryService
    ) {
    }

    public function getTotalIncome(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $data = $this->dashboardQueryService->getTotalIncomeData($year);

        return response()->json(['data' => $data]);
    }

    public function getDeductibleExpenses(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $data = $this->dashboardQueryService->getDeductibleExpensesData($year, true);

        return response()->json([
            'data' => [
                'total_deductible' => $data['total'],
                'categories' => $data['categories']
            ]
        ]);
    }

    public function getNonDeductibleExpenses(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $data = $this->dashboardQueryService->getDeductibleExpensesData($year, false);

        return response()->json([
            'data' => [
                'total_non_deductible' => $data['total'],
                'categories' => $data['categories']
            ]
        ]);
    }

    public function getRevenueReport(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $data = $this->dashboardQueryService->getRevenueReportData($year);

        return response()->json(['data' => $data]);
    }

    public function getClientStats(Request $request): JsonResponse
    {
        $data = $this->dashboardQueryService->getClientStatsData();

        return response()->json(['data' => $data]);
    }

    public function getStats(Request $request): JsonResponse
    {
        try {
            $startDate = (string) $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = (string) $request->input('end_date', now()->endOfMonth()->toDateString());
            
            $stats = $this->orderQueryService->getMonthlyStats($startDate, $endDate);
            
            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error("ERROR in DashboardController::getStats: " . $e->getMessage());
            return response()->json([
                'units' => 0,
                'sales' => 0.0,
                'expenses' => 0.0,
                'profit' => 0.0,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUnitsSold(DashboardStatsRequest $request): UnitsSoldResource
    {
        $units = $this->orderQueryService->getUnitsSold(
            $request->validated('start_date'),
            $request->validated('end_date')
        );

        return new UnitsSoldResource($units);
    }

    public function getProfit(DashboardStatsRequest $request): ProfitResource
    {
        $profit = $this->orderQueryService->getProfit(
            $request->validated('start_date'),
            $request->validated('end_date')
        );

        return new ProfitResource($profit);
    }

    public function getPopularProducts(PopularProductsRequest $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $limit = (int) ($request->validated('limit', 5) ?? 5);
        $products = $this->orderQueryService->getPopularProducts($limit);

        return PopularProductResource::collection($products);
    }

    public function getAnalyticsData(Request $request): JsonResponse
    {
        $data = $this->dashboardQueryService->getAnalyticsDataOptimized();

        return response()->json($data);
    }

    public function getEmployeeSalesByAmount(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        return response()->json(['data' => $this->orderQueryService->getEmployeeSalesByAmount($year)]);
    }

    public function getEmployeeSalesByUnits(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        return response()->json(['data' => $this->orderQueryService->getEmployeeSalesByUnits($year)]);
    }

    public function getSoldExpiringProducts(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $movements = app(\App\Services\Expirations\ExpirationQueryService::class)->getSoldExpiringLotsThisMonth();
        return SoldExpiringProductResource::collection($movements);
    }
}
