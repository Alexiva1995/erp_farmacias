<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpiredLog;
use App\Models\ProductLot;
use App\Services\Expirations\ExpirationActionService;
use App\Services\Expirations\ExpirationQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
     * Marca un lote como caducado y redistribuye su costo.
     */
    public function expire(ProductLot $lot)
    {
        try {
            $this->actionService->expireLot($lot);

            return response()->json(['message' => 'Lote caducado y costo redistribuido con éxito.'], 200);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() === 400 ? 400 : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }
    public function getSummary()
    {
        // El servicio ahora devuelve una colección (array)
        $summaries = $this->queryService->getExpiredLotsSummary();

        // Devolvemos el array directamente. El frontend se encargará de mostrarlo.
        return response()->json($summaries);
    }

    /**
     * Obtiene una lista paginada de lotes ya expirados desde el servicio.
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
