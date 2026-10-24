<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Contracts\SplitCheckServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\SplitCheckRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class SplitCheckController extends Controller
{
    public function __construct(
        protected SplitCheckServiceInterface $splitCheckService
    ) {}

    public function split(SplitCheckRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['type'] === 'equal') {
            $parts = (int) $validated['parts'];
            $result = $this->splitCheckService->splitEqually($order, $parts);

            return response()->json([
                'message' => "Cuenta dividida exitosamente en {$parts} partes iguales.",
                'result' => $result,
            ]);
        }

        // Tipo: seats_items
        $splits = $validated['splits'] ?? [];
        $result = $this->splitCheckService->splitBySeatsOrItems($order, $splits);

        return response()->json([
            'message' => 'Cuenta dividida por ítems/comensales exitosamente.',
            'created_orders_count' => count($result),
            'parent_order_id' => $order->id,
        ]);
    }
}
