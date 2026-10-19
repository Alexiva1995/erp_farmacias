<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Zoom\ZoomServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tpv\AddZoomItemRequest;
use App\Http\Resources\Tpv\ZoomItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TpvZoomController extends Controller
{
    public function __construct(
        protected ZoomServiceInterface $zoomService
    ) {}

    /**
     * Obtener los datos del producto Zoom registrado o crearlo si no existe.
     */
    public function getProduct(): JsonResponse
    {
        try {
            $product = $this->zoomService->getZoomProduct();

            return response()->json([
                'success' => true,
                'data' => new ZoomItemResource(['product' => $product]),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al obtener producto Zoom: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al obtener el producto Zoom.',
            ], 500);
        }
    }

    /**
     * Agregar un ítem de Zoom a la orden activa del TPV.
     */
    public function addItem(AddZoomItemRequest $request): JsonResponse
    {
        try {
            $sellerId = Auth::id();
            $result = $this->zoomService->addZoomItemToOrder($request->validated(), $sellerId);

            return response()->json([
                'success' => true,
                'message' => 'Servicio Zoom agregado a la orden exitosamente.',
                'data' => new ZoomItemResource($result),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al agregar ítem Zoom a la orden: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al agregar el ítem de Zoom a la orden: ' . $e->getMessage(),
            ], 500);
        }
    }
}
