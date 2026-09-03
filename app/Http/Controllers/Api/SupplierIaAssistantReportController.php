<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\IaAssistantReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SupplierIaAssistantReportController extends Controller
{
    public function __construct(
        protected \App\Contracts\Product $product,
        protected \App\Services\Reports\IaAssistantReportService $iaAssistantReportService
    ) {
    }

    public function filtrarPaginate(IaAssistantReportRequest $request): JsonResponse
    {
        $filtros = $this->prepararFiltros($request);
        $respuestaConsulta = $this->iaAssistantReportService->getFilteredReportWithPaginate($filtros);

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    public function filtrarWithoutPaginate(IaAssistantReportRequest $request): JsonResponse
    {
        $filtros = $this->prepararFiltros($request);
        $respuestaConsulta = $this->iaAssistantReportService->getFilteredReportWithoutPaginate($filtros);

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    public function stats(IaAssistantReportRequest $request): JsonResponse
    {
        $filtros = $this->prepararFiltros($request);
        $stats = $this->iaAssistantReportService->getStatsReport($filtros);

        return ApiResponse::success($stats, "ok", 200);
    }

    private function prepararFiltros(IaAssistantReportRequest $request): array
    {
        $validated = $request->validated();

        $filtros = [
            "itemsPerPage" => (int) ($validated["itemsPerPage"] ?? 10),
            "page" => (int) ($validated["page"] ?? 1),
            "tipo_filtracion" => $validated["tipo_filtracion"] ?? 'combinado',
            "tipo_vista" => filter_var($validated["tipo_vista"] ?? false, FILTER_VALIDATE_BOOLEAN),
            "lapso_de_tiempo" => $validated["lapso_de_tiempo"] ?? '3 month',
            "with_suppliers" => filter_var($validated["with_suppliers"] ?? false, FILTER_VALIDATE_BOOLEAN),
            "con_descuento" => filter_var($validated["con_descuento"] ?? false, FILTER_VALIDATE_BOOLEAN),
            "with_trend" => filter_var($validated["with_trend"] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $validated["orderBy"];
            $filtros["sortBy"] = $validated["sortBy"];
        }

        if ($request->filled("q")) {
            $filtros["q"] = $validated["q"];
        }

        if ($request->filled("stock")) {
            $filtros["stock"] = $validated["stock"];
        }

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $validated["laboratoryId"];
        }

        if ($request->filled("groups")) {
            $filtros["groups"] = $validated["groups"];
        }

        if ($request->filled("isColombian")) {
            $filtros["isColombian"] = filter_var($validated["isColombian"], FILTER_VALIDATE_BOOLEAN);
        } elseif ($request->filled("is_colombia")) {
            $filtros["isColombian"] = filter_var($validated["is_colombia"], FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->filled("isNovaventa")) {
            $filtros["isNovaventa"] = filter_var($validated["isNovaventa"], FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->filled("product")) {
            $filtros["ids_in"] = $validated["product"];
        }

        if ($request->filled("hasStock") && $request->hasStock !== "all") {
            $filtros["hasStock"] = $request->hasStock === "with" || $request->hasStock === "true" || $request->hasStock === true;
        }

        return $filtros;
    }

    public function consultProduct(): JsonResponse
    {
        $respuesta = $this->product->consultProduct();
        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function exportarExcel(IaAssistantReportRequest $request)
    {
        $filtros = $this->prepararFiltros($request);
        $data = $this->iaAssistantReportService->getFilteredReportWithoutPaginate($filtros);
        
        $excel = new \App\Exports\AssistantReportProductExport(collect($data));
        $fileName = 'assistant-report-' . now()->format('Y-m-d') . '.' . ($request->formato ?? 'xlsx');

        return Excel::download($excel, $fileName);
    }

    public function clearIgnoreUntil(): JsonResponse
    {
        try {
            \App\Models\Product::whereNotNull('ignore_until')->update(['ignore_until' => null]);
            return ApiResponse::success(null, "Fechas de restricción limpiadas correctamente.", 200);
        } catch (\Exception $e) {
            Log::error("Error al limpiar ignore_until: " . $e->getMessage());
            return ApiResponse::error("Error al limpiar las fechas.", 500);
        }
    }
}
