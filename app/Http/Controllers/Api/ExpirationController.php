<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductLot;
use App\Services\Expirations\ExpirationActionService;
use App\Services\Expirations\ExpirationQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpirationController extends Controller
{
    public function __construct(
        private ExpirationQueryService $queryService,
        private ExpirationActionService $actionService
    ) {
    }

    /**
     * Obtiene una lista de lotes próximos a vencer.
     */
    public function index(Request $request)
    {
        $query = $this->queryService->getExpiringLotsQuery($request);

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
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
     * Reajusta los precios de productos caducados de un mes completo,
     * excluyendo los productos especificados.
     */
    public function adjustExpiredProductsPrices(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|string|date_format:Y-m',
            'excludedProductIds' => 'array',
            'excludedProductIds.*' => 'integer|exists:products,id',
        ]);

        try {
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
     * Reajusta el precio de un lote específico.
     * @deprecated Esta funcionalidad ya no se usa
     */
    public function adjustPrice(ProductLot $lot)
    {
        return response()->json(['message' => 'Esta funcionalidad ha sido deshabilitada.'], 400);
    }

    /**
     * Reajusta los precios de múltiples lotes.
     * @deprecated Esta funcionalidad ya no se usa
     */
    public function adjustMultiplePrices(Request $request)
    {
        return response()->json(['message' => 'Esta funcionalidad ha sido deshabilitada.'], 400);
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

        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage == -1) {
            $logs = $query->get();
            return response()->json([
                'data' => $logs,
                'total' => $logs->count(),
            ]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }

    /**
     * Marca múltiples lotes como caducados.
     */
    public function expireMultiple(Request $request)
    {
        $validated = $request->validate([
            'lot_ids' => 'required|array|min:1',
            'lot_ids.*' => 'integer',
        ]);

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
}
