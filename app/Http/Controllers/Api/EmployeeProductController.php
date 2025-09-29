<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeProduct\StoreEmployeeProductRequest;
use App\Models\Employee;
use App\Services\EmployeeProducts\EmployeeProductActionService;
use App\Services\EmployeeProducts\EmployeeProductQueryService;
use Illuminate\Http\Request;

class EmployeeProductController extends Controller
{
    public function __construct(
        private EmployeeProductQueryService $queryService,
        private EmployeeProductActionService $actionService
    ) {
    }

    /**
     * Listar empleados con sus productos asignados
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $result = $this->queryService->getFilteredEmployeeProducts($data);

        return ApiResponse::success([
            'data' => $result->items(),
            'total' => $result->total(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'last_page' => $result->lastPage(),
        ]);
    }

    /**
     * Asignar o actualizar productos a un empleado
     */
    public function store(StoreEmployeeProductRequest $request)
    {
        try {
            $validated = $request->validated();
            $result = $this->actionService->assignProducts($validated);

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Productos asignados correctamente'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Eliminar asignación específica de producto a empleado
     */
    public function destroy(Employee $employee, int $productId)
    {
        try {
            $result = $this->actionService->removeAssignment($employee, $productId);

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Asignación eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Obtener estadísticas de asignaciones
     */
    public function stats()
    {
        $stats = $this->queryService->getAssignmentStats();
        return ApiResponse::success($stats);
    }
}
