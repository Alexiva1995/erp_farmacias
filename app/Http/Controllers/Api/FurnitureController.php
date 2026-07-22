<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Furniture;
use App\Services\Furniture\FurnitureActionService;
use App\Services\Furniture\FurnitureQueryService;
use App\Http\Resources\FurnitureResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class FurnitureController extends Controller
{
    public function __construct(
        private FurnitureQueryService $furnitureQueryService,
        private FurnitureActionService $furnitureActionService
    ) {
    }

    /**
     * Lista el mobiliario con filtros y paginación
     */
    public function index(Request $request)
    {
        $query = $this->furnitureQueryService->getFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return FurnitureResource::collection($items);
        }

        $paginatedResult = $query->paginate($perPage);

        return FurnitureResource::collection($paginatedResult);
    }

    /**
     * Crea un nuevo mobiliario
     */
    public function store(\App\Http\Requests\StoreFurnitureRequest $request)
    {
        try {
            $furniture = $this->furnitureActionService->createFurniture($request->validated());

            return response()->json([
                'message' => 'Mobiliario creado con éxito.',
                'furniture' => new FurnitureResource($furniture)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el mobiliario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Muestra un mobiliario específico
     */
    public function show(Furniture $furniture)
    {
        return new FurnitureResource($furniture);
    }

    /**
     * Actualiza un mobiliario existente
     */
    public function update(\App\Http\Requests\UpdateFurnitureRequest $request, Furniture $furniture)
    {
        try {
            $updatedFurniture = $this->furnitureActionService->updateFurniture(
                $furniture,
                $request->validated()
            );

            return response()->json([
                'message' => 'Mobiliario actualizado con éxito.',
                'furniture' => new FurnitureResource($updatedFurniture)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el mobiliario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina un mobiliario
     */
    public function destroy(Furniture $furniture)
    {
        try {
            $this->furnitureActionService->deleteFurniture($furniture);

            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el mobiliario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el valor total del mobiliario considerando depreciación
     */
    public function getValue(Request $request)
    {
        $furnitureValue = $this->furnitureQueryService->calculateTotalValue();

        return response()->json([
            'data' => [
                'total_value' => $furnitureValue,
                'currency' => 'USD',
                'calculated_at' => now()->toISOString(),
                'description' => 'Valor actual del mobiliario con depreciación aplicada'
            ],
            'message' => 'Valor del mobiliario calculado con éxito.'
        ], 200);
    }

    /**
     * Obtiene el valor total de depreciación del mobiliario
     */
    public function getDepreciation(Request $request)
    {
        $totalDepreciation = $this->furnitureQueryService->calculateTotalDepreciation();

        return response()->json([
            'data' => [
                'total_depreciation' => $totalDepreciation,
                'currency' => 'USD',
                'calculated_at' => now()->toISOString(),
                'description' => 'Valor total depreciado de todo el mobiliario'
            ],
            'message' => 'Depreciación total calculada con éxito.'
        ], 200);
    }
}
