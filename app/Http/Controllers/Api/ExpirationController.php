<?php

namespace App\Http\Controllers\Api;

use App\Exports\ExpiringLotsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExpireMultipleRequest;
use App\Http\Requests\PriceAdjustmentRequest;
use App\Http\Resources\ExpirationResource;
use App\Http\Resources\ExpiredLogResource;
use App\Models\ProductLot;
use App\Services\Expirations\ExpirationActionService;
use App\Services\Expirations\ExpirationQueryService;
use App\Services\Resources\ResourceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExpirationController extends Controller
{
    public function __construct(
        private ExpirationQueryService $queryService,
        private ExpirationActionService $actionService,
        private ResourceService $resourceService
    ) {}

    /**
     * Obtiene una lista de lotes próximos a vencer.
     */
    public function index(Request $request)
    {
        $query = $this->queryService->getExpiringLotsQuery($request);

        $perPage = (int) $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $results = $query->get();
            return response()->json([
                'data' => ExpirationResource::collection($results),
                'total' => $results->count(),
            ]);
        }

        $paginatedResult = $query->paginate($perPage);
        
        return response()->json([
            'data' => ExpirationResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total(),
        ]);
    }

    /**
     * Obtiene una lista de lotes próximos a vencer sin paginar.
     */
    public function getExpiringAll(Request $request)
    {
        $query = $this->queryService->getExpiringLotsQuery($request);

        return ExpirationResource::collection($query->get());
    }

    /**
     * Marca un lote como caducado (sin redistribuir el costo).
     */
    public function expire(ProductLot $lot)
    {
        try {
            $this->actionService->expireLot($lot);

            return response()->json(['message' => 'Lote marcado como caducado con éxito.'], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() === 400 ? 400 : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * Previsualiza el reajuste de precios antes de aplicarlo.
     */
    public function previewPriceAdjustment(PriceAdjustmentRequest $request)
    {
        try {
            $validated = $request->validated();

            $previewData = $this->actionService->getAdjustmentPreview(
                $validated['month'],
                $validated['excludedProductIds'] ?? []
            );

            return response()->json($previewData);
        } catch (\Exception $e) {
            $statusCode = is_numeric($e->getCode()) && $e->getCode() > 0 ? $e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * Reajusta los precios de productos caducados de un mes completo,
     * excluyendo los productos especificados.
     */
    public function adjustExpiredProductsPrices(PriceAdjustmentRequest $request)
    {
        try {
            $validated = $request->validated();

            $result = $this->actionService->adjustExpiredProductsPricesWithExclusions(
                $validated['month'],
                $validated['excludedProductIds'] ?? []
            );

            if ($result['success']) {
                return response()->json([
                    'message' => $result['message'],
                    'processed_logs' => $result['processed_logs'],
                    'excluded_logs' => $result['excluded_logs'],
                    'total_cost_redistributed' => $result['total_cost_redistributed'],
                    'affected_products_count' => $result['affected_products_count'],
                    'total_units_affected' => $result['total_units_affected'],
                ], 200);
            } else {
                return response()->json([
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            $statusCode = $e->getCode() === 400 ? 400 : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * Verifica si ya se realizó un reajuste de precios en un mes específico.
     */
    public function checkMonthAdjustmentStatus($month)
    {
        try {
            $hasAdjustment = $this->actionService->hasMonthPriceAdjustment($month);

            return response()->json([
                'has_adjustment' => $hasAdjustment,
                'month' => $month
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene el resumen de lotes caducados por mes.
     */
    public function getSummary()
    {
        $summaries = $this->queryService->getExpiredLotsSummary();
        return response()->json($summaries);
    }

    /**
     * Obtiene una lista paginada de lotes ya expirados.
     */
    public function getLotExpired(Request $request)
    {
        $query = $this->queryService->getExpiredLotsLogQuery($request);

        $perPage = (int) $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $results = $query->get();
            return response()->json([
                'data' => ExpiredLogResource::collection($results),
                'total' => $results->count(),
            ]);
        }

        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => ExpiredLogResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total(),
        ]);
    }

    /**
     * Marca múltiples lotes como caducados.
     */
    public function expireMultiple(ExpireMultipleRequest $request)
    {
        $validated = $request->validated();

        $result = $this->actionService->expireMultipleLots($validated['lot_ids']);

        $successCount = $result['success_count'];
        $failedLots = $result['failed_lots'];

        if (empty($failedLots)) {
            return response()->json(['message' => "{$successCount} lotes caducados con éxito."], 200);
        }

        return response()->json([
            'message' => "Se procesaron {$successCount} lotes. " . count($failedLots) . " lotes fallaron.",
            'failed_lots' => $failedLots,
        ], 207);
    }

    /**
     * Exporta la lista de lotes próximos a vencer (PDF o Excel).
     */
    public function export(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $query = $this->queryService->getExpiringLotsQuery($request);
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            $lots = $query->get();
            
            $pdfContent = Pdf::loadView('exports.expiring-lots-pdf', compact('lots'))
                ->setPaper('a4', 'landscape');
                
            return $pdfContent->download('reporte-vencimientos-' . now()->format('Y-m-d') . '.pdf');
        }

        $fileName = 'reporte-vencimientos-' . now()->format('Y-m-d') . '.' . $format;
        return Excel::download(new ExpiringLotsExport($query), $fileName);
    }

    /**
     * Descarga el Acta de Desincorporación por Vencimiento de un mes específico.
     */
    public function downloadMonthlyReport(string $month)
    {
        ini_set('memory_limit', '512M');
        app()->setLocale('es');
        
        $data = $this->queryService->getExpiredLotsForMonth($month);
        
        if ($data['logs']->isEmpty()) {
            return response()->json(['message' => 'No hay registros de vencimiento para este periodo.'], 404);
        }

        $data['bs_rate'] = $this->resourceService->getExchangeRate('BS');

        $pdf = Pdf::loadView('exports.expirations-monthly-report', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download("acta-desincorporacion-{$month}.pdf");
    }
}

