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
        $filtros = $this->prepararFiltros($request);
        $respuestaConsulta = $this->iaAssistantReportService->getFilteredReportWithPaginate($filtros);

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }


    public function filtrarWithoutPaginate(Request $request): JsonResponse
    {
        $filtros = $this->prepararFiltros($request);
        $respuestaConsulta = $this->iaAssistantReportService->getFilteredReportWithoutPaginate($filtros);

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    public function stats(Request $request): JsonResponse
    {
        $filtros = $this->prepararFiltros($request);
        
        // Obtenemos todos los productos filtrados sin hidratar para mayor velocidad
        $items = $this->iaAssistantReportService->getFilteredReportWithoutPaginate($filtros);

        $stats = [
            'necesitan' => 0,
            'exceso' => 0,
            'ok' => 0
        ];

        foreach ($items as $item) {
            $solicitarVal = (float)($item->solicitar ?? 0);
            $loteQuantity = (float)($item->lote_quantity ?? 0);

            // Sincronizado con la lógica de UI y Repository
            if ($solicitarVal > 0 || ($solicitarVal == 0 && $loteQuantity <= 0)) {
                $stats['necesitan']++;
            } elseif ($solicitarVal < 0) {
                $stats['exceso']++;
            } else {
                $stats['ok']++;
            }
        }

        return ApiResponse::success($stats, "ok", 200);
    }

    private function prepararFiltros(Request $request): array
    {
        $filtros = [
            "itemsPerPage" => (int) ($request->itemsPerPage ?? 10),
            "page" => (int) ($request->page ?? 1),
            "tipo_filtracion" => $request->tipo_filtracion,
            "tipo_vista" => filter_var($request->tipo_vista, FILTER_VALIDATE_BOOLEAN),
            "lapso_de_tiempo" => $request->lapso_de_tiempo,
            "with_suppliers" => filter_var($request->with_suppliers, FILTER_VALIDATE_BOOLEAN),
            "con_descuento" => filter_var($request->con_descuento, FILTER_VALIDATE_BOOLEAN),
            "with_trend" => filter_var($request->with_trend, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        if ($request->filled("q")) {
            $filtros["q"] = $request->q;
        }

        if ($request->filled("stock")) {
            $filtros["stock"] = $request->stock;
        }

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("groups")) {
            $filtros["groups"] = $request->groups;
        }

        if ($request->filled("isColombian")) {
            $filtros["isColombian"] = filter_var($request->isColombian, FILTER_VALIDATE_BOOLEAN);
        }

        return $filtros;
    }

    public function consultProduct(): JsonResponse
    {
        $respuesta = $this->product->consultProduct();
        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function exportarExcel(Request $request)
    {
        $filtros = $this->prepararFiltros($request);
        $data = $this->iaAssistantReportService->getFilteredReportWithoutPaginate($filtros);
        
        $excel = new \App\Exports\AssistantReportProductExport(collect($data));
        $fileName = 'assistant-report-' . now()->format('Y-m-d') . '.' . $request->formato;

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
