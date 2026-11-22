<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\ApproveReturnRequest;
use App\Http\Requests\Returns\SearchReturnOrdersRequest;
use App\Http\Requests\Returns\StoreReturnRequest;
use App\Http\Resources\Returns\ReturnEntryResource;
use App\Http\Resources\Returns\ReturnOrderResource;
use App\Models\ReturnEntry;
use App\Services\Returns\ReturnsActionService;
use App\Services\Returns\ReturnsQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReturnsController extends Controller
{
    public function __construct(
        private ReturnsActionService $returnsActionService,
        private ReturnsQueryService $returnsQueryService,
    ) {
    }

    /**
     * Listar devoluciones paginadas con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->returnsQueryService->getQueryOrder($request);
        $perPage = (int) $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json([
                'data' => ReturnEntryResource::collection($items),
                'total' => $items->count(),
            ]);
        }

        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => ReturnEntryResource::collection($paginatedResult->getCollection()),
            'total' => $paginatedResult->total(),
        ]);
    }

    /**
     * Buscar órdenes completadas elegibles para devolución.
     */
    public function searchOrders(SearchReturnOrdersRequest $request): JsonResponse
    {
        try {
            $identification = trim((string) $request->input('identification', ''));
            $orderId = $request->filled('order_id') ? (int) $request->input('order_id') : null;

            if ($identification === '' && $orderId === null) {
                return response()->json(['data' => [], 'total' => 0]);
            }

            $ordersQuery = $this->returnsActionService->searchOrdersReturns(
                $identification,
                $request->validated(),
                $orderId
            );

            $perPage = (int) $request->input('itemsPerPage', 10);

            if ($perPage < 0) {
                $items = $ordersQuery->get();
                return response()->json([
                    'data' => ReturnOrderResource::collection($items),
                    'total' => $items->count(),
                ]);
            }

            $paginator = $ordersQuery->paginate($perPage);

            return response()->json([
                'data' => ReturnOrderResource::collection($paginator->getCollection()),
                'total' => $paginator->total(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error en searchOrders (returns):', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener lotes disponibles de un producto para devolución.
     */
    public function getProductLots(int $productId): JsonResponse
    {
        try {
            $lots = $this->returnsActionService->getProductLotsForReturn($productId);
            return response()->json($lots);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Registrar devolución (unificada: directa o pendiente según configuración).
     */
    public function returnsProduct(StoreReturnRequest $request): JsonResponse
    {
        try {
            $result = $this->returnsActionService->createReturns($request->validated());
            return response()->json([
                'success' => $result['success'],
                'approved' => $result['approved'],
                'fiscal_queued' => $result['fiscal_queued'],
                'message' => $result['message'],
                'data' => ReturnEntryResource::collection($result['returns']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Actualizar estado de una devolución (rechazar o aprobar simple).
     */
    public function updateReturnStatus(int $returnEntryId, string $status): JsonResponse
    {
        $returnEntry = ReturnEntry::findOrFail($returnEntryId);

        try {
            $return = $this->returnsActionService->updateStatus($returnEntry, $status);
            $msg = $status === ReturnEntry::APPROVED
                ? 'Devolución aprobada exitosamente.'
                : 'Devolución rechazada exitosamente.';
            return ApiResponse::success($msg, ['return' => new ReturnEntryResource($return)]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de devolución:', [
                'error' => $e->getMessage(),
                'returnEntry_id' => $returnEntry->id,
            ]);
            return ApiResponse::error('No se pudo actualizar la devolución: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Aprobar una devolución pendiente (con distribución opcional de lotes si aplica).
     */
    public function approve(ApproveReturnRequest $request, int $returnEntryId): JsonResponse
    {
        $returnEntry = ReturnEntry::findOrFail($returnEntryId);

        try {
            $restock = $request->has('restock_inventory')
                ? $request->boolean('restock_inventory')
                : null;

            $return = $this->returnsActionService->approveReturn(
                $returnEntry,
                $request->input('updated_lots', []),
                $request->input('new_lots', []),
                $restock
            );

            return ApiResponse::success(
                ['return' => new ReturnEntryResource($return)],
                'Devolución aprobada correctamente.'
            );
        } catch (\Exception $e) {
            Log::error('Error al aprobar devolución:', [
                'error' => $e->getMessage(),
                'returnEntry_id' => $returnEntry->id,
            ]);
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Distribuir cantidad devuelta en lotes para devoluciones ya aprobadas.
     */
    public function distributeLots(ApproveReturnRequest $request, int $returnEntryId): JsonResponse
    {
        $returnEntry = ReturnEntry::findOrFail($returnEntryId);

        try {
            $this->returnsActionService->distributeLots(
                $returnEntry,
                $request->input('updated_lots', []),
                $request->input('new_lots', [])
            );
            return ApiResponse::success('Cantidad distribuida en lotes correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al distribuir lotes (return):', [
                'error' => $e->getMessage(),
                'returnEntry_id' => $returnEntry->id,
            ]);
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Aprobar devolución con distribución obligatoria en lotes (compatibilidad).
     */
    public function approveWithDistribution(ApproveReturnRequest $request, int $returnEntryId): JsonResponse
    {
        $returnEntry = ReturnEntry::findOrFail($returnEntryId);

        try {
            $return = $this->returnsActionService->approveWithDistribution(
                $returnEntry,
                $request->input('updated_lots', []),
                $request->input('new_lots', [])
            );
            return ApiResponse::success(
                ['return' => new ReturnEntryResource($return)],
                'Devolución aprobada y cantidad distribuida en lotes correctamente.'
            );
        } catch (\Exception $e) {
            Log::error('Error al aprobar con distribución:', [
                'error' => $e->getMessage(),
                'returnEntry_id' => $returnEntry->id,
            ]);
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Reintentar emisión de Nota de Crédito fiscal de una devolución aprobada.
     */
    public function retryCreditNote(int $returnEntryId): JsonResponse
    {
        $returnEntry = ReturnEntry::findOrFail($returnEntryId);

        try {
            $return = $this->returnsActionService->retryCreditNote($returnEntry);
            return ApiResponse::success(
                ['return' => new ReturnEntryResource($return)],
                'Nota de Crédito reencolada para impresión fiscal.'
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
