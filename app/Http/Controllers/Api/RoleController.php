<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Role as RoleContract;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleMenuPermissionsRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function __construct(
        private RoleContract $roleServices
    ) {
    }

    /**
     * Listado para compatibilidad y endpoints de selección.
     */
    public function list(): JsonResponse
    {
        $roles = $this->roleServices->list();

        return ApiResponse::success(RoleResource::collection($roles));
    }

    /**
     * Listar todos los roles del sistema.
     */
    public function index(): AnonymousResourceCollection
    {
        $roles = $this->roleServices->list();

        return RoleResource::collection($roles);
    }

    /**
     * Obtener el detalle de un rol específico.
     */
    public function show(int $id): RoleResource
    {
        $role = $this->roleServices->findById($id);

        if (!$role) {
            abort(404, 'Rol no encontrado.');
        }

        return new RoleResource($role);
    }

    /**
     * Crear un nuevo rol.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleServices->create($request->validated());

        return response()->json([
            'message' => 'Rol creado exitosamente.',
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * Actualizar datos generales de un rol.
     */
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = $this->roleServices->update($id, $request->validated());

        return response()->json([
            'message' => 'Rol actualizado exitosamente.',
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Actualizar exclusivamente los permisos de menú de un rol.
     */
    public function updateMenuPermissions(UpdateRoleMenuPermissionsRequest $request, int $id): JsonResponse
    {
        $role = $this->roleServices->updateMenuPermissions(
            $id,
            $request->validated('menu_permissions')
        );

        return response()->json([
            'message' => 'Permisos de menú actualizados exitosamente.',
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Eliminar un rol si no tiene dependencias.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->roleServices->delete($id);

        return response()->json([
            'message' => 'Rol eliminado exitosamente.',
        ]);
    }
}
