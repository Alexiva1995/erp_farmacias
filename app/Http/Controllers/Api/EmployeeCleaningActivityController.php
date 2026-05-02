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
     * Obtener una lista plana de todas las asignaciones
     */
    public function assignments(Request $request)
    {
        try {
            $data = $request->all();
            $result = $this->queryService->getAllAssignments($data);

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
     * Obtener ejecuciones programadas del empleado logueado
     */
    public function myActivities(Request $request)
    {
        try {
            $data = $request->all();
            $result = $this->queryService->getMyExecutions($data);

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
     * Actualizar el estado de una ejecución del empleado logueado
     */
    public function updateMyActivityStatus(Request $request, int $executionId)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:Pendiente,Procesada',
                'photo' => 'required_if:status,Procesada|image|mimes:jpeg,png,jpg|max:5120',
                'notes' => 'nullable|string|max:500',
            ]);

            $user = auth()->user();
            $employee = $user->employee;

            if (!$employee) {
                return ApiResponse::error('No tienes un perfil de empleado asociado', 403);
            }

            $result = $this->actionService->updateExecutionStatus(
                $employee->id,
                $executionId,
                $validated,
                $request->file('photo')
            );

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Estado actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    // =========================================================================
    // MÉTODOS PARA SUPERVISOR
    // =========================================================================

    /**
     * Obtener ejecuciones para revisión del supervisor
     */
    public function supervisorExecutions(Request $request)
    {
        try {
            $data = $request->all();
            $result = $this->queryService->getSupervisorExecutions($data);

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
     * Aprobar una ejecución (supervisor)
     */
    public function approveExecution(Request $request, int $executionId)
    {
        try {
            $validated = $request->validate([
                'notes' => 'nullable|string|max:500',
            ]);

            $user = auth()->user();

            $result = $this->actionService->approveExecution(
                $executionId,
                $user->id,
                $validated['notes'] ?? null
            );

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Actividad aprobada exitosamente'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Rechazar una ejecución y devolverla a pendiente (supervisor)
     */
    public function rejectExecution(Request $request, int $executionId)
    {
        try {
            $validated = $request->validate([
                'rejection_reason' => 'required|string|max:500',
            ]);

            $user = auth()->user();

            $result = $this->actionService->rejectExecution(
                $executionId,
                $user->id,
                $validated['rejection_reason']
            );

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Actividad devuelta al empleado'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Cancelar una ejecución (supervisor)
     */
    public function cancelExecution(Request $request, int $executionId)
    {
        try {
            $validated = $request->validate([
                'cancellation_reason' => 'required|string|max:500',
            ]);

            $user = auth()->user();

            $result = $this->actionService->cancelExecution(
                $executionId,
                $user->id,
                $validated['cancellation_reason']
            );

            return ApiResponse::success([
                'status' => $result,
                'message' => 'Actividad cancelada'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Obtener estadísticas del supervisor
     */
    public function supervisorStats()
    {
        try {
            $stats = $this->queryService->getSupervisorStats();
            return ApiResponse::success($stats);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
}
