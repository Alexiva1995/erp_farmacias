<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeLaboratory\StoreEmployeeLaboratoryRequest;
use App\Models\Employee;
use App\Services\EmployeeLaboratories\EmployeeLaboratoryActionService;
use App\Services\EmployeeLaboratories\EmployeeLaboratoryQueryService;
use Illuminate\Http\Request;

class EmployeeLaboratoryController extends Controller
{
    public function __construct(
        private EmployeeLaboratoryQueryService $queryService,
        private EmployeeLaboratoryActionService $actionService
    ) {
    }

    /**
     * Listar empleados con sus laboratorios asignados
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $result = $this->queryService->getFilteredEmployeeLaboratories($data);

        return ApiResponse::success([
            'data' => $result->items(),
            'total' => $result->total(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'last_page' => $result->lastPage(),
        ]);
    }

    /**
     * Asignar o actualizar laboratorios a un empleado
     */
    public function store(StoreEmployeeLaboratoryRequest $request)
    {
        try {
            $validated = $request->validated();
            $result = $this->actionService->assignLaboratories($validated);

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Laboratorios asignados correctamente'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Eliminar asignación específica de laboratorio a empleado
     */
    public function destroy(Employee $employee, int $laboratoryId)
    {
        try {
            $result = $this->actionService->removeAssignment($employee, $laboratoryId);

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
