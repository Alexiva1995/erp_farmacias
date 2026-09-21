<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\FiscalZReportFilterRequest;
use App\Http\Resources\Fiscal\FiscalZReportResource;
use App\Services\Fiscal\FiscalZReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalZReportController extends Controller
{
    public function __construct(
        private FiscalZReportService $zReportService
    ) {}

    /**
     * Listado paginado de reportes Z fiscales con KPIs resumidos.
     */
    public function index(FiscalZReportFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) $request->input('itemsPerPage', 15);
        $sortBy = $request->input('sortBy', 'report_date');
        $orderBy = $request->input('orderBy', 'desc');

        $paginator = $this->zReportService->getReports($filters, $perPage, $sortBy, $orderBy);
        $summary = $this->zReportService->getSummaryStats($filters);

        return response()->json([
            'data'    => FiscalZReportResource::collection($paginator->items()),
            'total'   => $paginator->total(),
            'summary' => $summary,
        ]);
    }

    /**
     * Obtener el detalle de un Reporte Z específico por ID.
     */
    public function show(int $id): JsonResponse
    {
        $report = $this->zReportService->getReportById($id);

        if (!$report) {
            return response()->json([
                'message' => 'Reporte Z no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => new FiscalZReportResource($report),
        ]);
    }

    /**
     * Generar o recalcular Reporte Z para una fecha específica bajo demanda.
     */
    public function generate(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $force = (bool) $request->input('force', false);
        $number = $request->has('number') ? (int) $request->input('number') : null;

        $report = $this->zReportService->generateForDate($date, $number, $force);

        return response()->json([
            'message' => 'Reporte Z procesado exitosamente.',
            'data'    => new FiscalZReportResource($report),
        ]);
    }

    /**
     * Sube y verifica la imagen del reporte Z usando Gemini.
     */
    public function verifyImage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // max 5MB
        ]);

        $file = $request->file('image');
        $result = $this->zReportService->verifyImageWithAi($id, $file);

        return response()->json([
            'message' => 'Verificación completada',
            'data'    => new FiscalZReportResource($result),
        ]);
    }
}
