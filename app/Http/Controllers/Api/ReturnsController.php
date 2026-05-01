<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\ReturnEntry;
use Illuminate\Http\Request;
use App\Services\Returns\ReturnsActionService;
use App\Services\Returns\ReturnsQueryService;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;

class ReturnsController extends Controller
{

    public function __construct(
        private ReturnsActionService $returnsActionService,
        private ReturnsQueryService $returnsQueryService,
    ) {
    }

    public function index(Request $request)
    {

        $query = $this->returnsQueryService->getQueryOrder($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function searchOrders(Request $request)
    {
        try {
            $identification = $request->input('identification');
            \Log::info('Controlador searchOrders recibiendo:', ['identification' => $identification, 'type' => gettype($identification)]);
            if (empty($identification) || !is_string($identification)) {
                return response()->json(['data' => [], 'total' => 0]);
            }

            $ordersQuery = $this->returnsActionService->searchOrdersReturns(
                trim($identification),
                $request->all()
            );

            $perPage = $request->input('itemsPerPage', 10);

            if ($perPage < 0) {
                $items = $ordersQuery->get();
                return response()->json(['data' => $items, 'total' => $items->count()]);
            }

            $paginator = $ordersQuery->paginate($perPage);

            $mappedItems = $paginator->getCollection()->map(function ($order) {
                $orderArray = $order->toArray();
                $currency = strtoupper($order->currency ?? 'USD');
                $details = $orderArray['details'] ?? [];
                $orderArray['details'] = collect($details)->map(function ($detail) use ($currency) {
                    $detail['currency'] = $currency;
                    return $detail;
                })->all();
                return $orderArray;
            });

            $paginator->setCollection($mappedItems);

            return response()->json([
                'data' => $paginator->getCollection()->all(),
                'total' => $paginator->total()
            ]);
        } catch (\Exception $e) {
            Log::error('Error en searchOrders (returns):', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProductLots(Request $request, $productId)
    {
        try {
            $lots = $this->returnsActionService->getProductLotsForReturn($productId);
            return response()->json($lots);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function returnsProduct(Request $request)
    {
        try {
            $result = $this->returnsActionService->productReturn($request);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function updateReturnStatus($returnEntryId, string $status)
    {

        $ReturnEntry = ReturnEntry::findOrFail($returnEntryId);

        try {
            $return = $this->returnsActionService->updateStatus($ReturnEntry, $status);
            return ApiResponse::success('Devolución aprobada exitosamente.', ['return' => $return]);
        } catch (\Exception $e) {
            Log::error('Error al aprobar la devolución:', ['error' => $e->getMessage(), 'returnEntry_id' => $ReturnEntry->id]);
            return ApiResponse::error('No se pudo aprobar la devolución: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Distribuir cantidad devuelta en lotes (reingreso a inventario). Solo para devoluciones ya aprobadas.
     */
    public function distributeLots(Request $request, $returnEntryId)
    {
        $returnEntry = ReturnEntry::findOrFail($returnEntryId);
        $updatedLots = $request->input('updated_lots', []);
        $newLots = $request->input('new_lots', []);

        try {
            $this->returnsActionService->distributeLots($returnEntry, $updatedLots, $newLots);
            return ApiResponse::success('Cantidad distribuida en lotes correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al distribuir lotes (return):', ['error' => $e->getMessage(), 'returnEntry_id' => $returnEntry->id]);
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Aprobar devolución solo después de distribuir las unidades en lotes (obligatorio).
     * Primero aplica la distribución (stock actual + unidades devueltas), luego aprueba.
     */
    public function approveWithDistribution(Request $request, $returnEntryId)
    {
        $returnEntry = ReturnEntry::findOrFail($returnEntryId);
        $updatedLots = $request->input('updated_lots', []);
        $newLots = $request->input('new_lots', []);

        try {
            $return = $this->returnsActionService->approveWithDistribution($returnEntry, $updatedLots, $newLots);
            return ApiResponse::success(['return' => $return], 'Devolución aprobada y cantidad distribuida en lotes correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al aprobar con distribución:', ['error' => $e->getMessage(), 'returnEntry_id' => $returnEntry->id]);
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}

