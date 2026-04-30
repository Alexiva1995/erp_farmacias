<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use App\Models\GroupsLaboratory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LaboratoryManagementController extends Controller
{
    /**
     * Listar laboratorios con su grupo
     */
    public function index(Request $request): JsonResponse
    {
        $query = Laboratory::with('group')->withCount('products');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $sortBy = $request->get('sortBy', 'name');
        $orderBy = $request->get('orderBy', 'asc');
        $itemsPerPage = (int) $request->get('itemsPerPage', 10);

        // Si es -1 (opción "All" de Vuetify), paginamos con el total de registros
        if ($itemsPerPage === -1) {
            $itemsPerPage = $query->count() ?: 10;
        }

        return response()->json($query->orderBy($sortBy, $orderBy)->paginate($itemsPerPage));
    }

    /**
     * Listar todos los grupos
     */
    public function groups(): JsonResponse
    {
        return response()->json(GroupsLaboratory::orderBy('name')->get());
    }

    /**
     * Guardar o actualizar laboratorio
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'group_id' => 'nullable|exists:groups_laboratories,id',
            'parent_id' => 'nullable|exists:laboratories,id'
        ]);

        $lab = Laboratory::updateOrCreate(
            ['id' => $request->id],
            $request->only(['name', 'group_id', 'parent_id'])
        );

        return response()->json(['message' => 'Laboratorio guardado correctamente', 'laboratory' => $lab]);
    }

    /**
     * Crear grupo y asignar laboratorios
     */
    public function storeGroup(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'laboratory_ids' => 'nullable|array',
            'laboratory_ids.*' => 'exists:laboratories,id'
        ]);

        $group = GroupsLaboratory::updateOrCreate(
            ['id' => $request->id],
            ['name' => $request->name]
        );

        if ($request->has('laboratory_ids')) {
            // Asignar grupo a los laboratorios seleccionados
            Laboratory::whereIn('id', $request->laboratory_ids)->update(['group_id' => $group->id]);
            
            // Opcional: Desasignar los que ya no están en la lista (si es edición completa)
            if ($request->id) {
                Laboratory::where('group_id', $group->id)
                    ->whereNotIn('id', $request->laboratory_ids)
                    ->update(['group_id' => null]);
            }
        }

        return response()->json(['message' => 'Grupo guardado y laboratorios asignados', 'group' => $group]);
    }

    /**
     * Eliminar laboratorio.
     */
    public function destroy(Laboratory $laboratory): JsonResponse
    {
        // Desvincular productos antes de borrar (dejar laboratory_id en NULL)
        // Se usa withoutGlobalScopes() para asegurar que incluso productos borrados suavemente 
        // o filtrados por scopes personalizados (como not_deleted) se desvinculen
        // y no causen errores de integridad referencial.
        $laboratory->products()->withoutGlobalScopes()->update(['laboratory_id' => null]);

        // Limpiar relaciones pivot con empleados y proveedores
        // Se protegen con try-catch por si las tablas pivot no existen en el entorno actual
        try {
            $laboratory->employees()->detach();
        } catch (\Throwable $e) {
            \Log::warning("No se pudo desvincular empleados del laboratorio {$laboratory->id}: " . $e->getMessage());
        }

        try {
            $laboratory->suppliers()->detach();
        } catch (\Throwable $e) {
            \Log::warning("No se pudo desvincular proveedores del laboratorio {$laboratory->id}: " . $e->getMessage());
        }

        $laboratory->delete();

        return response()->json(['message' => 'Laboratorio eliminado y productos desvinculados']);
    }
}
