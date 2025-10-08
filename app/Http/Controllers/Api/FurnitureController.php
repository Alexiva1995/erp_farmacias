<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Furniture;
use App\Services\Furniture\FurnitureActionService;
use App\Services\Furniture\FurnitureQueryService;
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
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }

        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total()
        ]);
    }

    /**
     * Crea un nuevo mobiliario
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0.01',
            'acquisition_year' => 'required|integer|min:2000|max:' . date('Y'),
            'annual_depreciation_rate' => 'required|numeric|min:0|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $furniture = $this->furnitureActionService->createFurniture($validator->validated());

            return response()->json([
                'message' => 'Mobiliario creado con éxito.',
                'furniture' => $furniture
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
        return response()->json(['data' => $furniture]);
    }

    /**
     * Actualiza un mobiliario existente
     */
    public function update(Request $request, Furniture $furniture)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0.01',
            'acquisition_year' => 'required|integer|min:2000|max:' . date('Y'),
            'annual_depreciation_rate' => 'required|numeric|min:0|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $updatedFurniture = $this->furnitureActionService->updateFurniture(
                $furniture,
                $validator->validated()
            );

            return response()->json([
                'message' => 'Mobiliario actualizado con éxito.',
                'furniture' => $updatedFurniture
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
