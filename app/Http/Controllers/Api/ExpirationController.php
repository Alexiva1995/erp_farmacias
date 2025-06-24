<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductLot;
use App\Services\Expirations\ExpirationActionService;
use App\Services\Expirations\ExpirationQueryService;
use Illuminate\Http\Request;

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
}
