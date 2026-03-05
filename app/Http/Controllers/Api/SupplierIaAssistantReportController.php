<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Helpers\Algoritmo;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use DateTime;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SupplierIaAssistantReportController extends Controller
{
    //

    public function __construct(
        protected \App\Contracts\Product $product,
        protected \App\Services\Reports\IaAssistantReportService $iaAssistantReportService
    ) {
    }


    public function filtrarPaginate(Request $request): JsonResponse
    {
        $respuestaConsulta = $this->iaAssistantReportService->getFilteredReportWithPaginate($request->all());

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }


    public function filtrarWithoutPaginate(Request $request): JsonResponse
    {
        $respuestaConsulta = $this->iaAssistantReportService->getFilteredReportWithoutPaginate($request->all());

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    public function consultProduct(): JsonResponse
    {
        $respuesta = $this->product->consultProduct();
        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function exportarExcel(Request $request)
    {
        $data = $this->iaAssistantReportService->getFilteredReportWithoutPaginate($request->all());
        
        $excel = new \App\Exports\AssistantReportProductExport(collect($data));
        $fileName = 'assistant-report-' . now()->format('Y-m-d') . '.' . $request->formato;

        return Excel::download($excel, $fileName);
    }
}
