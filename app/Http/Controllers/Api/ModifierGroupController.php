<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ModifierGroupServiceInterface;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreModifierGroupRequest;
use App\Http\Requests\Restaurant\UpdateModifierGroupRequest;
use App\Http\Resources\ModifierGroupResource;
use App\Models\ModifierGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ModifierGroupController extends Controller
{
    public function __construct(
        protected ModifierGroupServiceInterface $modifierGroupService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $groups = $this->modifierGroupService->getAll();
        return ModifierGroupResource::collection($groups);
    }

    public function store(StoreModifierGroupRequest $request): ModifierGroupResource
    {
        $group = $this->modifierGroupService->create($request->validated());
        return new ModifierGroupResource($group);
    }

    public function show(int $id): ModifierGroupResource
    {
        $group = $this->modifierGroupService->find($id);
        if (!$group) {
            abort(404, 'Grupo de modificadores no encontrado.');
        }
        return new ModifierGroupResource($group);
    }

    public function update(UpdateModifierGroupRequest $request, ModifierGroup $modifierGroup): ModifierGroupResource
    {
        $updated = $this->modifierGroupService->update($modifierGroup, $request->validated());
        return new ModifierGroupResource($updated);
    }

    public function destroy(ModifierGroup $modifierGroup): JsonResponse
    {
        $this->modifierGroupService->delete($modifierGroup);
        return ApiResponse::success(null, 'Grupo de modificadores eliminado exitosamente.');
    }
}
