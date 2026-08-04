<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\IncomeStatementFilterRequest;
use App\Http\Resources\Financial\FinancialStatementDetailResource;
use App\Http\Resources\Financial\FinancialStatementSummaryResource;
use App\Services\FinancialStatementService;
use Illuminate\Http\JsonResponse;

class FinancialStatementController extends Controller
{
    public function __construct(
        protected FinancialStatementService $service
    ) {}

    public function index(IncomeStatementFilterRequest $request): JsonResponse
    {
        $data = $this->service->calculateSummary(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('search')
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function getSummary(IncomeStatementFilterRequest $request): JsonResponse
    {
        $data = $this->service->calculateSummary(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('search')
        );

        return response()->json([
            'success' => true,
            'data'    => new FinancialStatementSummaryResource($data),
        ]);
    }

    public function getDetails(IncomeStatementFilterRequest $request): JsonResponse
    {
        $data = $this->service->getPaginatedDetails(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('search'),
            $request->input('type'),
            (int) $request->input('per_page', 50)
        );

        return response()->json([
            'success' => true,
            'data'    => new FinancialStatementDetailResource($data),
        ]);
    }

    public function reset(): JsonResponse
    {
        $resetDate = $this->service->resetReportDate();

        return response()->json([
            'success' => true,
            'message' => 'Estado de resultados reiniciado con éxito. Se tomarán datos a partir de hoy.',
            'data'    => ['reset_date' => $resetDate],
        ]);
    }
}
