<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\StoreProductPackRequest;
use App\Http\Requests\Offers\UpdateProductPackRequest;
use App\Http\Resources\ProductPackResource;
use App\Models\ProductPack;
use App\Services\ProductPackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPackController extends Controller
{
    public function __construct(
        protected ProductPackService $packService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'search_id', 'is_active', 'sort_by', 'order', 'per_page']);
            $packs = $this->packService->listPacks($filters);

            return response()->json([
                'success' => true,
                'data' => ProductPackResource::collection($packs->items()),
                'total' => $packs->total(),
                'current_page' => $packs->currentPage(),
                'per_page' => $packs->perPage(),
                'last_page' => $packs->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los packs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreProductPackRequest $request): JsonResponse
    {
        try {
            $pack = $this->packService->createPack($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Pack creado exitosamente',
                'data' => new ProductPackResource($pack)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la configuración del pack',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pack: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(ProductPack $productPack): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ProductPackResource($productPack)
        ]);
    }

    public function update(UpdateProductPackRequest $request, ProductPack $productPack): JsonResponse
    {
        try {
            $updatedPack = $this->packService->updatePack($productPack, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Pack actualizado exitosamente',
                'data' => new ProductPackResource($updatedPack)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la configuración del pack',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el pack: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(ProductPack $productPack): JsonResponse
    {
        try {
            $this->packService->deletePack($productPack);

            return response()->json([
                'success' => true,
                'message' => 'Pack eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el pack: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus(ProductPack $productPack): JsonResponse
    {
        try {
            $updatedPack = $this->packService->toggleStatus($productPack);

            return response()->json([
                'success' => true,
                'message' => 'Estado del pack actualizado exitosamente',
                'data' => new ProductPackResource($updatedPack)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado del pack: ' . $e->getMessage()
            ], 500);
        }
    }
}
