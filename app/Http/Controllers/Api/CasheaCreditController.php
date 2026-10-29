<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashea\PayInstallmentRequest;
use App\Http\Resources\CasheaCreditResource;
use App\Http\Resources\CreditInstallmentResource;
use App\Models\CreditInstallment;
use App\Services\Credits\CasheaCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CasheaCreditController extends Controller
{
    public function __construct(
        private readonly CasheaCreditService $casheaCreditService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $itemsPerPage = (int) $request->input('itemsPerPage', 15);
        $credits = $this->casheaCreditService->getFilteredQuery($request)->paginate($itemsPerPage);

        return response()->json([
            'data' => CasheaCreditResource::collection($credits->items()),
            'total' => $credits->total(),
            'current_page' => $credits->currentPage(),
            'last_page' => $credits->lastPage(),
        ]);
    }

    public function kpis(): JsonResponse
    {
        $kpis = $this->casheaCreditService->getSummaryKpis();

        return response()->json([
            'data' => $kpis,
        ]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $installments = $this->casheaCreditService->getInstallmentsSchedule($request);

        return response()->json([
            'data' => CreditInstallmentResource::collection($installments->items()),
            'total' => $installments->total(),
            'current_page' => $installments->currentPage(),
            'last_page' => $installments->lastPage(),
        ]);
    }

    public function payInstallment(PayInstallmentRequest $request, CreditInstallment $installment): JsonResponse
    {
        $updated = $this->casheaCreditService->payInstallment($installment, $request->validated());

        return response()->json([
            'message' => 'Cuota registrada como cobrada exitosamente.',
            'data' => new CreditInstallmentResource($updated),
        ]);
    }
}
