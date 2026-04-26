<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\AbcReportRequest;
use App\Http\Resources\Bi\AbcReportResource;
use App\Services\Bi\AbcReportService;
use Illuminate\Http\JsonResponse;

/**
 * Clase AbcReportController
 * 
 * Gestiona el endpoint de Inteligencia de Negocios para el Reporte ABC Multicriterio.
 * Mantiene la responsabilidad única y la separación de capas a través de un controlador delgado.
 */
class AbcReportController extends Controller
{
    /**
     * @param AbcReportService $service
     */
    public function __construct(
        protected AbcReportService $service
    ) {
    }

    /**
     * Endpoint central para generar y obtener el reporte de Inteligencia de Negocios.
     * Realiza toda la extracción y ordenamiento en memoria para entregar los datos a Vue.
     *
     * @param AbcReportRequest $request
     * @return JsonResponse
     */
    public function generateReport(AbcReportRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        
        // El servicio maneja todo el cruce y generación del reporte
        $reportData = $this->service->getCalculatedAbcReport($filtros);

        // Si se provee sortBy / orderBy, reordenamos en base a los cálculos finales
        $sortBy = $filtros['sortBy'] ?? 'total_sales';
        $orderBy = $filtros['orderBy'] ?? 'desc';

        if ($orderBy === 'desc') {
            $reportData = $reportData->sortByDesc($sortBy)->values();
        } else {
            $reportData = $reportData->sortBy($sortBy)->values();
        }

        // Paginación Manual (al requerir cálculos porcentuales masivos 
        // sobre el volumen total es más seguro procesar y luego paginar la colección)
        $perPage = (int) ($filtros['itemsPerPage'] ?? 10);
        $page = (int) ($filtros['page'] ?? 1);

        if ($perPage === -1) {
            $paginatedItems = $reportData;
            $items = AbcReportResource::collection($paginatedItems);
            $total = $reportData->count();
        } else {
            $paginatedItems = $reportData->slice(($page - 1) * $perPage, $perPage)->values();
            $items = AbcReportResource::collection($paginatedItems);
            $total = $reportData->count();
        }

        // Calcular Estadísticas Globales (Summary) para KPIs del Dashboard
        $totalSalesGlobal = $reportData->sum('total_sales');
        $totalMarginAmtGlobal = $reportData->sum('margin_amount');
        
        $summary = [
            'total_sales' => (float) $totalSalesGlobal,
            'avg_margin' => $totalSalesGlobal > 0 ? ($totalMarginAmtGlobal / $totalSalesGlobal) * 100 : 0,
            'aax_products' => $reportData->filter(fn($i) => str_starts_with($i->final_classification, 'AA'))->count(),
            'frozen_capital' => (float) $reportData->sum('inventory_value'),
            // Conteo por clasificación de ventas
            'count_a' => $reportData->filter(fn($i) => $i->class_sales === 'A')->count(),
            'count_b' => $reportData->filter(fn($i) => $i->class_sales === 'B')->count(),
            'count_c' => $reportData->filter(fn($i) => $i->class_sales === 'C')->count(),
            'critical_stockouts' => $reportData->filter(fn($i) => ($i->class_sales === 'A' || $i->class_sales === 'B') && $i->current_stock <= 0)->count(),
            'total_products' => $reportData->count(),
        ];

        return response()->json([
            'data' => $items,
            'summary' => $summary,
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => $perPage > 0 ? ceil($total / $perPage) : 1
            ]
        ]);
    }
}
