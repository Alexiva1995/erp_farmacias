<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\KdsServiceInterface;
use App\Enums\KitchenStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\UpdateKitchenStatusRequest;
use App\Http\Resources\KdsTicketResource;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KdsController extends Controller
{
    public function __construct(
        protected KdsServiceInterface $kdsService
    ) {}

    /**
     * Listar comandas y platos activos en KDS.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $stationId = $request->query('station_id') ? (int) $request->query('station_id') : null;
        $orders = $this->kdsService->getActiveTickets($stationId);

        return KdsTicketResource::collection($orders);
    }

    /**
     * Enviar orden o platos a cocina (Comandar).
     */
    public function sendToKitchen(Request $request, Order $order): JsonResponse
    {
        $detailIds = $request->input('detail_ids', null);
        $result = $this->kdsService->sendOrderToKitchen($order, is_array($detailIds) ? $detailIds : null);

        return response()->json([
            'message' => 'Comanda enviada a cocina con éxito.',
            'data' => $result,
        ]);
    }

    /**
     * Actualizar estado de preparación de un plato (queued -> preparing -> ready -> served).
     */
    public function updateItemStatus(UpdateKitchenStatusRequest $request, OrderDetail $orderDetail): JsonResponse
    {
        $newStatus = KitchenStatusEnum::from($request->validated('status'));
        $updated = $this->kdsService->updateItemStatus($orderDetail, $newStatus);

        return response()->json([
            'message' => 'Estado de cocina actualizado con éxito.',
            'data' => [
                'id' => $updated->id,
                'kitchen_status' => $updated->kitchen_status->value,
                'kitchen_status_label' => $updated->kitchen_status->label(),
                'prepared_at' => $updated->prepared_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Despachar comanda completa (Bump Ticket).
     */
    public function bumpTicket(Request $request, Order $order): JsonResponse
    {
        $stationId = $request->input('station_id') ? (int) $request->input('station_id') : null;
        $this->kdsService->bumpTicket($order, $stationId);

        return response()->json([
            'message' => 'Comanda despachada con éxito.',
            'order_id' => $order->id,
        ]);
    }
}
