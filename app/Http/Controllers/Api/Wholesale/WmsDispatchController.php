<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wholesale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wholesale\CompletePackingRequest;
use App\Http\Requests\Wholesale\CompletePickingRequest;
use App\Http\Requests\Wholesale\DispatchOrderRequest;
use App\Http\Resources\Wholesale\DeliveryRouteResource;
use App\Http\Resources\Wholesale\WmsOrderResource;
use App\Models\DeliveryRoute;
use App\Services\Wholesale\WmsPickingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WmsDispatchController extends Controller
{
    public function __construct(
        protected WmsPickingService $wmsService
    ) {}

    /**
     * Listado de órdenes en cola de almacén (Picking, Checking, Packing, Despacho).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'workflow_status',
            'delivery_route_id',
            'search',
        ]);

        $perPage = (int) $request->input('per_page', 20);
        $orders = $this->wmsService->getWmsOrders($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => WmsOrderResource::collection($orders)->response()->getData(true),
        ]);
    }

    /**
     * Inicia el proceso de Picking.
     */
    public function startPicking(int $orderId): JsonResponse
    {
        $pickerId = (int) (auth()->id() ?: 1);
        $order = $this->wmsService->startPicking($orderId, $pickerId);

        return response()->json([
            'success' => true,
            'message' => 'Picking iniciado con éxito.',
            'data' => new WmsOrderResource($order->fresh(['client', 'deliveryRoute', 'picker', 'checker', 'details.product', 'details.lot'])),
        ]);
    }

    /**
     * Completa el proceso de Picking y avanza a Doble Chequeo.
     */
    public function completePicking(int $orderId, CompletePickingRequest $request): JsonResponse
    {
        $order = $this->wmsService->completePicking($orderId, (array) $request->input('items', []));

        return response()->json([
            'success' => true,
            'message' => 'Picking completado. La orden pasa a Doble Chequeo (Checking).',
            'data' => new WmsOrderResource($order->fresh(['client', 'deliveryRoute', 'picker', 'checker', 'details.product', 'details.lot'])),
        ]);
    }

    /**
     * Doble chequeo y embalaje final (Packing con conteo de bultos y cavas de frío).
     */
    public function completePacking(int $orderId, CompletePackingRequest $request): JsonResponse
    {
        $checkerId = (int) (auth()->id() ?: 1);
        $order = $this->wmsService->completeCheckingAndPacking(
            $orderId,
            $checkerId,
            (int) $request->input('packages_count', 1),
            (int) $request->input('cold_boxes_count', 0),
            (array) $request->input('items', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'Orden embalada (PACKED) y lista para despacho.',
            'data' => new WmsOrderResource($order->fresh(['client', 'deliveryRoute', 'picker', 'checker', 'details.product', 'details.lot'])),
        ]);
    }

    /**
     * Despacho hacia la ruta de entrega (precarga la factura en la farmacia si está en el ERP).
     */
    public function dispatchOrder(int $orderId, DispatchOrderRequest $request): JsonResponse
    {
        $routeId = $request->filled('delivery_route_id') ? (int) $request->input('delivery_route_id') : null;
        $order = $this->wmsService->dispatchOrder($orderId, $routeId);

        return response()->json([
            'success' => true,
            'message' => 'Orden despachada en ruta de entrega.',
            'data' => new WmsOrderResource($order->fresh(['client', 'deliveryRoute', 'picker', 'checker', 'details.product', 'details.lot'])),
        ]);
    }

    /**
     * Listado de rutas de despacho activas.
     */
    public function routes(): JsonResponse
    {
        $this->wmsService->ensureWmsSchema();

        try {
            if (!Schema::hasTable('delivery_routes')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $routes = DeliveryRoute::where('is_active', true)->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => DeliveryRouteResource::collection($routes),
            ]);
        } catch (\Throwable $e) {
            Log::warning("[WmsDispatchController] Error al obtener rutas: " . $e->getMessage());

            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }
    }
}
