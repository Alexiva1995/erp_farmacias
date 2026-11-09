<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Contracts\KitchenWasteServiceInterface;
use App\Contracts\ProductionOrderServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\RejectKitchenWasteRequest;
use App\Http\Requests\Restaurant\StoreKitchenWasteRequest;
use App\Http\Requests\Restaurant\StoreProductionOrderRequest;
use App\Http\Requests\Restaurant\StoreSubRecipeRequest;
use App\Http\Resources\Restaurant\KitchenWasteResource;
use App\Http\Resources\Restaurant\ProductionOrderResource;
use App\Http\Resources\Restaurant\SubRecipeResource;
use App\Models\KitchenWaste;
use App\Models\ProductionOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

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
        try {
            $userId = $request->user()?->id ?? 1;
            $order = $this->productionService->createAndExecute($request->validated(), $userId);

            return response()->json([
                'message' => 'Lote de producción completado e ingresado a inventario exitosamente.',
                'order' => new ProductionOrderResource($order),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al registrar orden de producción: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error al registrar orden de producción: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function cancelProduction(ProductionOrder $productionOrder): JsonResponse
    {
        try {
            $this->productionService->cancelOrder($productionOrder);
            return response()->json(['message' => 'Orden de producción cancelada.']);
        } catch (\Throwable $e) {
            Log::error('Error al cancelar orden de producción: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'id' => $productionOrder->id,
            ]);

            return response()->json([
                'message' => 'Error al cancelar orden de producción: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function storeSubRecipe(StoreSubRecipeRequest $request): JsonResponse
    {
        try {
            \App\Models\Dish::ensureRestaurantSchema();
            $subRecipe = $this->productionService->createSubRecipe($request->validated());

            return response()->json([
                'message' => 'Sub-receta creada y producto de inventario configurado exitosamente.',
                'data' => new SubRecipeResource($subRecipe),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al guardar sub-receta: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error al guardar la sub-receta: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateSubRecipe(StoreSubRecipeRequest $request, \App\Models\Dish $dish): JsonResponse
    {
        try {
            \App\Models\Dish::ensureRestaurantSchema($dish->getConnection());
            $subRecipe = $this->productionService->updateSubRecipe($dish, $request->validated());

            return response()->json([
                'message' => 'Sub-receta actualizada y costos recalculados exitosamente.',
                'data' => new SubRecipeResource($subRecipe),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar sub-receta: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar la sub-receta: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function listWaste(Request $request): JsonResponse
    {
        $filters = $request->all();
        $wastes = $this->wasteService->listWastes($filters);

        return response()->json($wastes);
    }

    public function storeWaste(StoreKitchenWasteRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id ?? 1;
            $waste = $this->wasteService->recordWaste(
                $request->validated(),
                $userId,
                $request->file('photo')
            );

            return response()->json([
                'message' => 'Merma registrada como pendiente de aprobación por el administrador.',
                'waste' => new KitchenWasteResource($waste),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al registrar merma: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->except('photo'),
            ]);

            return response()->json([
                'message' => 'Error al registrar merma: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approveWaste(Request $request, KitchenWaste $kitchenWaste): JsonResponse
    {
        try {
            $user = $request->user();
            $roleName = strtolower($user?->role?->name ?? '');
            $isAuthorized = in_array((int) ($user?->role_id ?? 0), [1, 2, 4], true)
                || str_contains($roleName, 'admin')
                || str_contains($roleName, 'supervisor');

            if (!$isAuthorized) {
                return response()->json([
                    'message' => 'No tienes permisos para aprobar mermas.',
                ], 403);
            }

            $approved = $this->wasteService->approveWaste($kitchenWaste, (int) $user->id);

            return response()->json([
                'message' => 'Merma aprobada exitosamente. Se descontó del inventario y se registró como pérdida.',
                'waste' => new KitchenWasteResource($approved),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al aprobar merma: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'waste_id' => $kitchenWaste->id,
            ]);

            return response()->json([
                'message' => 'Error al aprobar merma: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function rejectWaste(RejectKitchenWasteRequest $request, KitchenWaste $kitchenWaste): JsonResponse
    {
        try {
            $user = $request->user();
            $roleName = strtolower($user?->role?->name ?? '');
            $isAuthorized = in_array((int) ($user?->role_id ?? 0), [1, 2, 4], true)
                || str_contains($roleName, 'admin')
                || str_contains($roleName, 'supervisor');

            if (!$isAuthorized) {
                return response()->json([
                    'message' => 'No tienes permisos para rechazar mermas.',
                ], 403);
            }

            $rejected = $this->wasteService->rejectWaste(
                $kitchenWaste,
                (int) $user->id,
                $request->validated()['rejection_reason'] ?? null
            );

            return response()->json([
                'message' => 'Merma rechazada. El inventario no ha sido afectado.',
                'waste' => new KitchenWasteResource($rejected),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al rechazar merma: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'waste_id' => $kitchenWaste->id,
            ]);

            return response()->json([
                'message' => 'Error al rechazar merma: ' . $e->getMessage(),
            ], 500);
        }
    }
}
