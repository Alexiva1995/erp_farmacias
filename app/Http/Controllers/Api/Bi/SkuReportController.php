<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\SkuReportService;
use App\Http\Resources\Bi\SkuReportResource;
use Illuminate\Http\Request;

class SkuReportController extends Controller
{
    protected $skuReportService;

    public function __construct(SkuReportService $skuReportService)
    {
        $this->skuReportService = $skuReportService;
    }

    /**
     * Genera el reporte de Margen por SKU basado en las ventas concretadas y mermas.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateReport(Request $request)
    {
        $filters = $request->only([
            'start_date',
            'end_date',
            'laboratory_id',
            'group_id',
            'semaphore', // verde, amarillo, rojo, negro
            'sortBy',
            'orderBy'
        ]);

        $perPage = $request->input('itemsPerPage', 15);
        
        $paginatedReport = $this->skuReportService->generateReport($filters, $perPage);

        // Si hay filtrado por Semáforo después de calculado
        if (!empty($filters['semaphore'])) {
             // Este filtro se hace en memoria después de calcular
             $filteredItems = collect($paginatedReport->items())
                ->where('semaphore', $filters['semaphore'])
                ->values();
             
             // Nota: La paginación real se romperá si se filtra POST cálculo intenso, 
             // pero a nivel MVP es eficaz para no cargar a la BDD.
             $paginatedReport->setCollection($filteredItems);
        }

        return response()->json([
            'data' => SkuReportResource::collection($paginatedReport->getCollection())->resolve(),
            'total' => $paginatedReport->total(),
            'current_page' => $paginatedReport->currentPage(),
            'last_page' => $paginatedReport->lastPage()
        ]);
    }
}
