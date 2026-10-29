<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wholesale;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRoute;
use App\Services\Wholesale\WmsPickingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'data' => $orders,
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
            'data' => $order,
        ]);
    }

    /**
     * Completa el proceso de Picking y avanza a Doble Chequeo.
     */
    public function completePicking(int $orderId, Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.order_detail_id' => 'required|integer|exists:order_details,id',
            'items.*.picked_quantity' => 'required|numeric|min:0',
        ]);

        $order = $this->wmsService->completePicking($orderId, $request->items);

        return response()->json([
            'success' => true,
            'message' => 'Picking completado. La orden pasa a Doble Chequeo (Checking).',
            'data' => $order,
        ]);
    }

    /**
     * Doble chequeo y embalaje final (Packing con conteo de bultos y cavas de frío).
     */
    public function completePacking(int $orderId, Request $request): JsonResponse
    {
        $request->validate([
            'packages_count' => 'required|integer|min:1',
            'cold_boxes_count' => 'nullable|integer|min:0',
            'items' => 'required|array|min:1',
            'items.*.order_detail_id' => 'required|integer|exists:order_details,id',
            'items.*.checked_quantity' => 'required|numeric|min:0',
        ]);

        $checkerId = (int) (auth()->id() ?: 1);
        $order = $this->wmsService->completeCheckingAndPacking(
            $orderId,
            $checkerId,
            (int) $request->packages_count,
            (int) ($request->cold_boxes_count ?? 0),
            $request->items
        );

        return response()->json([
            'success' => true,
            'message' => 'Orden embalada (PACKED) y lista para despacho.',
            'data' => $order,
        ]);
    }

    /**
     * Despacho hacia la ruta de entrega (precarga la factura en la farmacia si está en el ERP).
     */
    public function dispatchOrder(int $orderId, Request $request): JsonResponse
    {
        $routeId = $request->filled('delivery_route_id') ? (int) $request->delivery_route_id : null;
        $order = $this->wmsService->dispatchOrder($orderId, $routeId);

        return response()->json([
            'success' => true,
            'message' => 'Orden despachada en ruta de entrega.',
            'data' => $order,
        ]);
    }

    /**
     * Listado de rutas de despacho activas.
     */
    public function routes(): JsonResponse
    {
        $routes = DeliveryRoute::where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $routes,
        ]);
    }
}
