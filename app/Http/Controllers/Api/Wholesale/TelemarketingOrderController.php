<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wholesale;

use App\Http\Controllers\Controller;
use App\Services\Wholesale\WholesaleOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemarketingOrderController extends Controller
{
    public function __construct(
        protected WholesaleOrderService $orderService
    ) {}

    /**
     * Crea un pedido mayorista B2B (aplicando división automática por plazos de crédito).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
            'delivery_route_id' => 'nullable|integer|exists:delivery_routes,id',
        ]);

        $result = $this->orderService->createWholesaleOrder($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pedido mayorista procesado exitosamente.',
            'data' => $result,
        ], 201);
    }

    /**
     * Aprueba el crédito de una orden retenida por cupo o mora.
     */
    public function approveCredit(int $orderId, Request $request): JsonResponse
    {
        $approverId = (int) (auth()->id() ?: 1);
        $order = $this->orderService->approveCreditOrder($orderId, $approverId);

        return response()->json([
            'success' => true,
            'message' => 'Crédito aprobado. La orden ha avanzado a cola de Almacén (Picking).',
            'data' => $order,
        ]);
    }
}
