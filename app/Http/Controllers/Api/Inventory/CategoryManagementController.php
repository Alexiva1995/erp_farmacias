<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreCategoryRequest;
use App\Http\Resources\Inventory\CategoryManagementResource;
use App\Models\Category;
use App\Services\Inventory\CategoryManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clase CategoryManagementController
 *
 * Gestiona el ABM/CRUD de categorías de inventario y menú en el módulo de administración.
 */
class CategoryManagementController extends Controller
{
    public function __construct(
        private readonly CategoryManagementService $categoryService
    ) {}

    /**
     * Listar categorías con conteo de productos y platos.
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->categoryService->paginate($request->only([
            'search',
            'type',
            'sortBy',
            'orderBy',
            'itemsPerPage',
        ]));

        return response()->json($paginator);
    }

    /**
     * Guardar o actualizar una categoría.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->save($request->validated());

        return response()->json([
            'message' => 'Categoría guardada correctamente.',
            'category' => (new CategoryManagementResource($category))->resolve(),
        ]);
    }

    /**
     * Ejecutar la categorización automática de productos mediante Inteligencia Artificial.
     */
    public function aiCategorize(): JsonResponse
    {
        try {
            $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
            if (empty($apiKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se ha configurado la clave GEMINI_API_KEY en el servidor.',
                ], 422);
            }

            $result = $this->categoryService->runAiCategorization();

            if ($result['exit_code'] !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo completar la categorización con IA: ' . $result['output'],
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Productos categorizados con Inteligencia Artificial exitosamente.',
                'output' => $result['output'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar una categoría y desvincular productos y platos.
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->delete($category);

        return response()->json([
            'message' => 'Categoría eliminada y elementos desvinculados correctamente.',
        ]);
    }
}
