<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
        $query = Category::withCount(['products', 'dishes']);

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $sortBy = $request->get('sortBy', 'name');
        $orderBy = $request->get('orderBy', 'asc');
        $itemsPerPage = (int) $request->get('itemsPerPage', 10);

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
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

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
