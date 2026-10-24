<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Contracts\PreBillServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\GeneratePreBillRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class PreBillController extends Controller
{
    public function __construct(
        protected PreBillServiceInterface $preBillService
    ) {}

    public function generate(GeneratePreBillRequest $request, Order $order): JsonResponse
    {
        $percentage = (float) ($request->validated()['service_percentage'] ?? 10.0);
        $ticketData = $this->preBillService->generatePreBill($order, $percentage);

        return response()->json([
            'message' => 'Pre-cuenta generada exitosamente.',
            'pre_bill' => $ticketData,
        ]);
    }

    public function cancel(Order $order): JsonResponse
    {
        $this->preBillService->cancelPreBill($order);

        return response()->json([
            'message' => 'Pre-cuenta cancelada y mesa devuelta a estado ocupado.',
        ]);
    }
}
