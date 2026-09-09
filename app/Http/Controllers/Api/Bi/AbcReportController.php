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
            'expiring_risk_capital' => (float) $reportData->filter(fn($i) => (float)$i->current_stock > 0 && $i->has_expiration_risk && (float)($i->risk_expiring_units ?? 0) > 0)->sum(fn($i) => (float)($i->risk_expiring_capital ?? $i->inventory_value)),
            'expiring_risk_count' => $reportData->filter(fn($i) => (float)$i->current_stock > 0 && $i->has_expiration_risk && (float)($i->risk_expiring_units ?? 0) > 0)->count(),
            // Conteo por clasificación de ventas
            'count_a' => $reportData->filter(fn($i) => $i->class_sales === 'A')->count(),
            'count_b' => $reportData->filter(fn($i) => $i->class_sales === 'B')->count(),
            'count_c' => $reportData->filter(fn($i) => $i->class_sales === 'C')->count(),
            'critical_stockouts' => $reportData->filter(fn($i) => ($i->class_sales === 'A' || $i->class_sales === 'B') && $i->current_stock <= 0)->count(),
            'negative_margin_count' => $reportData->filter(fn($i) => ((float)($i->margin_percentage ?? 0) < 0 || (float)($i->margin_amount ?? 0) < 0) && (float)($i->current_stock ?? 0) > 0)->count(),
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

    /**
     * Exporta los datos del Reporte ABC a Excel con soporte para los cuadrantes estratégicos.
     *
     * @param AbcReportRequest $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(AbcReportRequest $request)
    {
        $filtros = $request->validated();
        $exportType = $filtros['export_type'] ?? 'all';

        // Obtener el cálculo completo de datos
        $reportData = $this->service->getCalculatedAbcReport($filtros);

        $sheetTitle = 'Reporte ABC';
        $fileNamePrefix = 'reporte_abc';

        if ($exportType === 'ax_ay') {
            // 1. Cuadrante AX / AY: Clase A en Ventas con rotación X o Y (Top Prioridad Compras)
            $reportData = $reportData->filter(function ($item) {
                return $item->class_sales === 'A' && in_array($item->class_rotation, ['X', 'Y']);
            })->sortBy('inventory_days')->values();
            
            $sheetTitle = 'Prioridad Compras AX-AY';
            $fileNamePrefix = 'compras_prioritarias_ax_ay';
        } elseif ($exportType === 'frozen_capital') {
            // 2. Capital Congelado / CZ / Stock Muerto / Sobrestock en baja rotación o riesgo caducidad FEFO
            $reportData = $reportData->filter(function ($item) {
                if ((float) $item->current_stock <= 0) {
                    return false;
                }
                $isZeroSales = $item->sold_units <= 0;
                $isCZ = ($item->class_sales === 'C' && $item->class_rotation === 'Z');
                $isLowRotationExcess = ($item->class_sales === 'C' || $item->class_rotation === 'Z') && (float) $item->inventory_days >= 180;
                $isExpiringRisk = (bool) ($item->has_expiration_risk ?? false);

                return $isZeroSales || $isCZ || $isLowRotationExcess || $isExpiringRisk;
            })->sortByDesc('inventory_value')->values();

            $sheetTitle = 'Capital Congelado CZ';
            $fileNamePrefix = 'capital_congelado_cz';
        } elseif ($exportType === 'expiring_risk') {
            // 3. Riesgo de Expiración / FEFO (Capital próximo a caducar o con riesgo FEFO)
            $reportData = $reportData->filter(function ($item) {
                if ((float) $item->current_stock <= 0) {
                    return false;
                }
                $hasExpRisk = (bool) ($item->has_expiration_risk ?? false);
                $isExpiringSoon = (bool) ($item->is_expiring_soon ?? false);
                $daysToExp = $item->days_to_expiration !== null ? (int) $item->days_to_expiration : 9999;

                return $hasExpRisk || $isExpiringSoon || $daysToExp <= 180;
            })->sortBy(fn($i) => $i->days_to_expiration ?? 9999)->values();

            $sheetTitle = 'Riesgo Expiracion FEFO';
            $fileNamePrefix = 'riesgo_expiracion_fefo';
        } elseif ($exportType === 'gmroi') {
            // 4. Matriz de Rentabilidad GMROI (Top Retorno)
            $reportData = $reportData->sortByDesc('gmroi')->values();

            $sheetTitle = 'Rentabilidad GMROI';
            $fileNamePrefix = 'rentabilidad_gmroi';
        } elseif ($exportType === 'negative_margin') {
            // 5. Margen Negativo / Pérdida (< 0% con stock > 0)
            $reportData = $reportData->filter(function ($item) {
                return ((float)$item->margin_percentage < 0 || (float)$item->margin_amount < 0)
                    && (float)$item->current_stock > 0;
            })->sortBy('margin_percentage')->values();

            $sheetTitle = 'Margen Negativo con Stock';
            $fileNamePrefix = 'margen_negativo_con_stock';
        } else {
            // Ordenamiento por defecto solicitado
            $sortBy = $filtros['sortBy'] ?? 'total_sales';
            $orderBy = $filtros['orderBy'] ?? 'desc';

            if ($orderBy === 'desc') {
                $reportData = $reportData->sortByDesc($sortBy)->values();
            } else {
                $reportData = $reportData->sortBy($sortBy)->values();
            }
        }

        $fileName = $fileNamePrefix . '_' . now()->format('Ymd_His') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AbcReportExport($reportData, $sheetTitle),
            $fileName
        );
    }
}

