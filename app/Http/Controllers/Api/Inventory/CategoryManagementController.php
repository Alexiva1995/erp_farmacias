<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Http\Requests\Inventory\StoreCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Clase CategoryManagementController
 *
 * Gestiona el ABM/CRUD de categorías de inventario en el módulo de administración.
 */
class CategoryManagementController extends Controller
{
    /**
     * Listar categorías de inventario con conteo de productos y platos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $enabledTypes = config('branding.enabled_product_types', ['ingredients']);
        // Verificar si el tipo 'ingredients' está habilitado para cargar el conteo de platos
        $hasIngredients = in_array('ingredients', (array) $enabledTypes);

        $query = Category::query()
            ->select(['id', 'name'])
            ->withCount(['products']);

        if ($hasIngredients) {
            $query->withCount(['dishes']);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $sortBy = $request->get('sortBy', 'name');
        $orderBy = $request->get('orderBy', 'asc');
        $itemsPerPage = (int) $request->get('itemsPerPage', 10);

        if (!in_array($sortBy, ['id', 'name', 'products_count', 'dishes_count'])) {
            $sortBy = 'name';
        }
        $orderBy = strtolower($orderBy) === 'desc' ? 'desc' : 'asc';

        if ($itemsPerPage === -1) {
            $itemsPerPage = Category::count() ?: 10;
        }

        return response()->json($query->orderBy($sortBy, $orderBy)->paginate($itemsPerPage));
    }

    /**
     * Guardar o actualizar una categoría.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::updateOrCreate(
            ['id' => $request->id],
            ['name' => $request->name]
        );

        // Limpiar cachés de recursos
        \Cache::forget('resources.categories');
        \Cache::forget('resources.categories.dishes');

        return response()->json([
            'message' => 'Categoría guardada correctamente.',
            'category' => $category,
        ]);
    }

    /**
     * Ejecutar la categorización automática de productos mediante Inteligencia Artificial.
     *
     * @return JsonResponse
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

            // Ejecutar categorización con IA mediante Gemini
            $exitCode = \Illuminate\Support\Facades\Artisan::call('products:ai-categorize', [
                '--limit' => 60,
                '--batch' => 30,
            ]);

            $output = \Illuminate\Support\Facades\Artisan::output();

            \Cache::forget('resources.categories');
            \Cache::forget('resources.categories.dishes');

            if ($exitCode !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo completar la categorización con IA: ' . trim($output),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Productos categorizados con Inteligencia Artificial exitosamente.',
                'output' => trim($output),
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
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function destroy(Category $category): JsonResponse
    {
        // Desvincular productos y platos antes de eliminar
        $category->products()->update(['category_id' => null]);
        $category->dishes()->update(['category_id' => null]);

        $category->delete();

        // Limpiar cachés de recursos
        \Cache::forget('resources.categories');
        \Cache::forget('resources.categories.dishes');

        return response()->json([
            'message' => 'Categoría eliminada y elementos desvinculados correctamente.',
        ]);
    }
}
