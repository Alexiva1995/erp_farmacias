<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeCleaningActivity\StoreEmployeeCleaningActivityRequest;
use App\Models\Employee;
use App\Services\EmployeeCleaningActivities\EmployeeCleaningActivityActionService;
use App\Services\EmployeeCleaningActivities\EmployeeCleaningActivityQueryService;
use Illuminate\Http\Request;

class EmployeeCleaningActivityController extends Controller
{
    public function __construct(
        private EmployeeCleaningActivityQueryService $queryService,
        private EmployeeCleaningActivityActionService $actionService
    ) {
    }

    /**
     * Listar empleados con sus actividades de limpieza asignadas
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $result = $this->queryService->getFilteredEmployeeActivities($data);

        return ApiResponse::success([
            'data' => $result->items(),
            'total' => $result->total(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'last_page' => $result->lastPage(),
        ]);
    }

    /**
     * Asignar o actualizar actividades de limpieza a un empleado
     */
    public function store(StoreEmployeeCleaningActivityRequest $request)
    {
        try {
            $validated = $request->validated();
            $result = $this->actionService->assignActivities($validated);

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Actividades asignadas correctamente'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Eliminar asignación específica de actividad a empleado
     */
    public function destroy(Employee $employee, int $activityId)
    {
        try {
            $result = $this->actionService->removeAssignment($employee, $activityId);

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Asignación eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Actualizar el estado de una actividad asignada
     */
    public function updateStatus(Request $request, Employee $employee, int $activityId)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:Pendiente,Completada,Cancelada',
                'completed_date' => 'nullable|date',
                'notes' => 'nullable|string|max:500',
            ]);

            $result = $this->actionService->updateActivityStatus(
                $employee->id,
                $activityId,
                $validated
            );

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Estado actualizado correctamente'
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

    /**
     * Obtener actividades del empleado logueado
     */
    public function myActivities(Request $request)
    {
        try {
            $data = $request->all();
            $result = $this->queryService->getMyActivities($data);

            return ApiResponse::success([
                'data' => $result->items(),
                'total' => $result->total(),
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'last_page' => $result->lastPage(),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Actualizar el estado de una actividad del empleado logueado
     */
    public function updateMyActivityStatus(Request $request, int $activityId)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:Pendiente,Completada,Cancelada',
                'completed_date' => 'nullable|date',
                'notes' => 'nullable|string|max:500',
            ]);

            // Obtener el empleado del usuario logueado
            $user = auth()->user();
            $employee = $user->employee;

            if (!$employee) {
                return ApiResponse::error('No tienes un perfil de empleado asociado', 403);
            }

            $result = $this->actionService->updateActivityStatus(
                $employee->id,
                $activityId,
                $validated
            );

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Estado actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
}
