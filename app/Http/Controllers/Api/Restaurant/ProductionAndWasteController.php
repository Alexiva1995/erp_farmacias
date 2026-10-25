<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Contracts\KitchenWasteServiceInterface;
use App\Contracts\ProductionOrderServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreKitchenWasteRequest;
use App\Http\Requests\Restaurant\StoreProductionOrderRequest;
use App\Http\Resources\Restaurant\KitchenWasteResource;
use App\Http\Resources\Restaurant\ProductionOrderResource;
use App\Models\ProductionOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionAndWasteController extends Controller
{
    public function __construct(
        protected ProductionOrderServiceInterface $productionService,
        protected KitchenWasteServiceInterface $wasteService
    ) {}

    public function listProduction(Request $request): JsonResponse
    {
        $orders = $this->productionService->listOrders($request->all());
        return response()->json($orders);
    }

    public function storeProduction(StoreProductionOrderRequest $request): JsonResponse
    {
        $userId = $request->user()?->id ?? 1;
        $order = $this->productionService->createAndExecute($request->validated(), $userId);

        return response()->json([
            'message' => 'Lote de producción completado e ingresado a inventario exitosamente.',
            'order' => new ProductionOrderResource($order),
        ], 201);
    }

    public function cancelProduction(ProductionOrder $productionOrder): JsonResponse
    {
        $this->productionService->cancelOrder($productionOrder);
        return response()->json(['message' => 'Orden de producción cancelada.']);
    }

    public function listWaste(Request $request): JsonResponse
    {
        $wastes = $this->wasteService->listWastes($request->all());
        return response()->json($wastes);
    }

    public function storeWaste(StoreKitchenWasteRequest $request): JsonResponse
    {
        $userId = $request->user()?->id ?? 1;
        $waste = $this->wasteService->recordWaste($request->validated(), $userId);

        return response()->json([
            'message' => 'Merma / Baja de cocina registrada correctamente.',
            'waste' => new KitchenWasteResource($waste),
        ], 201);
    }
}
