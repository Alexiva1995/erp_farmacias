<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use App\Models\GroupsLaboratory;
use App\Http\Requests\Inventory\StoreLaboratoryRequest;
use App\Http\Requests\Inventory\StoreLaboratoryGroupRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LaboratoryManagementController extends Controller
{
    /**
     * Listar laboratorios con su grupo
     */
    public function index(Request $request): JsonResponse
    {
        $query = Laboratory::query()
            ->select([
                'laboratories.id',
                'laboratories.name',
                'laboratories.group_id',
                'laboratories.created_at',
                'laboratories.updated_at',
            ])
            ->with(['group:id,name'])
            ->leftJoin('products', 'laboratories.id', '=', 'products.laboratory_id')
            ->leftJoin('product_lots', 'products.id', '=', 'product_lots.product_id')
            ->selectRaw('COUNT(DISTINCT products.id) as products_count, COALESCE(SUM(product_lots.quantity), 0) as units_count')
            ->groupBy(
                'laboratories.id',
                'laboratories.name',
                'laboratories.group_id',
                'laboratories.created_at',
                'laboratories.updated_at'
            );

        if ($request->search) {
            $query->where('laboratories.name', 'like', "%{$request->search}%");
        }

        $sortBy = $request->get('sortBy', 'units_count');
        $orderBy = $request->get('orderBy', 'desc');
        $itemsPerPage = (int) $request->get('itemsPerPage', 10);

        // Si es -1 (opción "All" de Vuetify), paginamos con el total de registros
        if ($itemsPerPage === -1) {
            $itemsPerPage = Laboratory::count() ?: 10;
        }

        if ($sortBy === 'id' || $sortBy === 'name') {
            $sortBy = "laboratories.{$sortBy}";
        }

        $laboratories = $query->orderBy($sortBy, $orderBy)->paginate($itemsPerPage);

        return response()->json([
            'data' => \App\Http\Resources\LaboratoryManageResource::collection($laboratories->items()),
            'total' => $laboratories->total(),
            'current_page' => $laboratories->currentPage(),
            'per_page' => $laboratories->perPage(),
        ]);
    }

    /**
     * Listar todos los grupos
     */
    public function groups(): JsonResponse
    {
        $groups = GroupsLaboratory::with(['laboratories:id,name,group_id'])
            ->orderBy('name')
            ->get();

        return response()->json($groups);
    }

    /**
     * Guardar o actualizar laboratorio
     */
    public function store(StoreLaboratoryRequest $request): JsonResponse
    {
        $lab = Laboratory::updateOrCreate(
            ['id' => $request->id],
            $request->only(['name', 'group_id', 'parent_id'])
        );

        // Limpiar la caché de laboratorios para que se actualice la lista en los selects
        \Cache::forget('resources.laboratories');

        return response()->json(['message' => 'Laboratorio guardado correctamente', 'laboratory' => $lab]);
    }

    /**
     * Crear grupo y asignar laboratorios
     */
    public function storeGroup(StoreLaboratoryGroupRequest $request): JsonResponse
    {
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

        // Limpiar la caché de laboratorios para que desaparezca de los selects
        \Cache::forget('resources.laboratories');

        return response()->json(['message' => 'Laboratorio eliminado y productos desvinculados']);
    }

    /**
     * Eliminar grupo de laboratorios.
     */
    public function destroyGroup(GroupsLaboratory $group): JsonResponse
    {
        // Al borrar el grupo, los laboratorios asociados deben quedar con group_id = null
        Laboratory::where('group_id', $group->id)->update(['group_id' => null]);
        
        $group->delete();
        
        return response()->json(['message' => 'Grupo eliminado correctamente']);
    }
}
