<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\GroupsProduct;
use App\Services\Groups\GroupActionService;
use App\Services\Groups\GroupQueryService;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(
        private GroupQueryService $queryService,
        private GroupActionService $actionService
    ) {
    }

    /**
     * Obtiene una lista paginada de grupos.
     */
    public function index(Request $request)
    {
        $paginatedResult = $this->queryService->getPaginatedGroups($request);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }

    /**
     * Almacena un nuevo grupo en la base de datos.
     */
    public function store(StoreGroupRequest $request)
    {
        $group = $this->actionService->createGroup($request->validated());

        return response()->json([
            'message' => 'Grupo creado con éxito.',
            'group' => $group
        ], 201);
    }

    /**
     * Actualiza un grupo existente.
     */
    public function update(UpdateGroupRequest $request, GroupsProduct $group)
    {
        $updatedGroup = $this->actionService->updateGroup($group, $request->validated());

        return response()->json([
            'message' => 'Grupo actualizado con éxito.',
            'group' => $updatedGroup
        ]);
    }

    /**
     * Elimina un grupo.
     */
    public function destroy(GroupsProduct $group)
    {
        $this->actionService->deleteGroup($group);

        return response()->noContent();
    }

    /**
     * Busca un grupo por ID o nombre.
     */
    public function search(Request $request)
    {
        $searchTerm = $request->query('q');
        if (empty($searchTerm)) {
            return response()->json(['message' => 'El término de búsqueda es requerido.'], 400);
        }

        $group = $this->queryService->findGroup($searchTerm);

        if ($group) {
            return response()->json($group);
        }

        return response()->json(['message' => 'Grupo no encontrado.'], 404);
    }
}
