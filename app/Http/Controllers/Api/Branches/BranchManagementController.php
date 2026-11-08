<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Branches;

use App\Contracts\Tenancy\BranchManagementServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Requests\Branches\UpdateBranchRequest;
use App\Http\Resources\Branches\BranchResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BranchManagementController extends Controller
{
    public function __construct(
        protected BranchManagementServiceInterface $branchService
    ) {}

    /**
     * Lista todas las sucursales del grupo empresarial actual.
     */
    public function index(Request $request): JsonResponse
    {
        $currentTenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : '';

        if (empty($currentTenantId)) {
            // Si está en central o no hay tenant activo, verificar si se especifica tenant_id
            $currentTenantId = (string) $request->query('tenant_id', '');
        }

        if (empty($currentTenantId)) {
            return response()->json(['data' => []]);
        }

        $branches = $this->branchService->listGroupBranches($currentTenantId);

        return response()->json([
            'data' => BranchResource::collection($branches),
        ]);
    }

    /**
     * Crea una nueva sucursal vinculada a la farmacia matriz.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $currentTenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : '';

        if (empty($currentTenantId)) {
            $currentTenantId = (string) $request->input('parent_tenant_id', '');
        }

        if (empty($currentTenantId)) {
            return response()->json(['message' => 'No se pudo identificar la empresa matriz.'], 422);
        }

        try {
            $branch = $this->branchService->createBranchForPharmacy($currentTenantId, $request->validated());

            return response()->json([
                'message' => 'Sucursal creada y aprovisionada exitosamente.',
                'branch' => new BranchResource($branch),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al crear la sucursal: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Actualiza los datos de una sucursal del grupo.
     */
    public function update(UpdateBranchRequest $request, string $branchId): JsonResponse
    {
        $currentTenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : '';

        if (empty($currentTenantId)) {
            return response()->json(['message' => 'Empresa no identificada.'], 422);
        }

        try {
            $branch = $this->branchService->updateBranch($currentTenantId, $branchId, $request->validated());

            return response()->json([
                'message' => 'Sucursal actualizada exitosamente.',
                'branch' => new BranchResource($branch),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar la sucursal: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Activa o desactiva la sucursal.
     */
    public function toggleActive(string $branchId): JsonResponse
    {
        $currentTenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : '';

        if (empty($currentTenantId)) {
            return response()->json(['message' => 'Empresa no identificada.'], 422);
        }

        try {
            $branch = $this->branchService->toggleBranchStatus($currentTenantId, $branchId);

            return response()->json([
                'message' => 'Estado de sucursal actualizado exitosamente.',
                'branch' => new BranchResource($branch),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al cambiar estado: ' . $e->getMessage(),
            ], 422);
        }
    }
}
