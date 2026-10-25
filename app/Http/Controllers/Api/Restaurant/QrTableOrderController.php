<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Contracts\QrTableOrderServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\SubmitQrOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrTableOrderController extends Controller
{
    public function __construct(
        protected QrTableOrderServiceInterface $qrService
    ) {}

    public function showTable(string $token): JsonResponse
    {
        $data = $this->qrService->getTableByToken($token);
        return response()->json($data);
    }

    public function submitOrder(SubmitQrOrderRequest $request, string $token): JsonResponse
    {
        $order = $this->qrService->submitQrOrder($token, $request->validated());

        return response()->json([
            'message' => '¡Pedido enviado a cocina exitosamente!',
            'order_id' => $order->id,
            'total_usd' => $order->total_amount_usd,
        ], 201);
    }

    public function requestAssistance(Request $request, string $token): JsonResponse
    {
        $reason = $request->input('reason', 'waiter_call');
        $this->qrService->requestAssistance($token, $reason);

        $msg = $reason === 'bill_requested' ? 'Cuenta solicitada. El mesero se acerca a su mesa.' : 'El mesero ha sido notificado.';
        return response()->json(['message' => $msg]);
    }
}
