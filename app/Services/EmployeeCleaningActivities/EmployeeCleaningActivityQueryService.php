<?php

namespace App\Services\EmployeeCleaningActivities;

use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

class EmployeeCleaningActivityQueryService
{
    /**
     * Obtiene una consulta filtrada de empleados con sus actividades de limpieza
     * 
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getFilteredEmployeeActivities(array $data): LengthAwarePaginator
    {
        $query = Employee::where('is_active', true)
            ->with([
                'cleaningActivities' => function ($query) {
                    $query->withPivot(['status', 'assigned_date', 'completed_date', 'notes'])
                        ->orderBy('activity', 'asc');
                }
            ])
            ->withCount('cleaningActivities')
            ->orderByRaw('photo IS NOT NULL DESC');

        // Búsqueda por nombre de empleado
        if (!empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('identification', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Filtro por estado de actividad
        if (!empty($data['status'])) {
            $status = $data['status'];
            $query->whereHas('cleaningActivities', function ($q) use ($status) {
                $q->where('employee_cleaning_activity.status', $status);
            });
        }

        // Filtro por actividad específica
        if (!empty($data['activity_id'])) {
            $query->whereHas('cleaningActivities', function ($q) use ($data) {
                $q->where('cleaning_activities.id', $data['activity_id']);
            });
        }

        // Ordenamiento
        if (!empty($data['sortBy']) && !empty($data['orderBy'])) {
            $sortBy = $data['sortBy'];
            $orderBy = $data['orderBy'];

            if ($sortBy === 'employee_name') {
                $query->orderBy('name', $orderBy)
                    ->orderBy('last_name', $orderBy);
            } elseif ($sortBy === 'activities_count') {
                $query->orderBy('cleaning_activities_count', $orderBy);
            } else {
                $query->orderBy($sortBy, $orderBy);
            }
        } else {
            $query->orderBy('name', 'asc');
        }

        // Paginación
        $itemsPerPage = $data['itemsPerPage'] ?? 10;
        $employees = $query->paginate($itemsPerPage);

        // Transformar datos para el frontend
        $employees->getCollection()->transform(function ($employee) {
            return $this->formatEmployeeData($employee);
        });

        return $employees;
    }

    /**
     * Formatea los datos del empleado para la respuesta
     * 
     * @param Employee $employee
     * @return array
     */
    private function formatEmployeeData(Employee $employee): array
    {
        return [
            'employee_id' => $employee->id,
            'employee_name' => trim($employee->name . ' ' . $employee->last_name),
            'photo_url' => $employee->photo_url,
            'identification' => $employee->identification,
            'is_active' => $employee->is_active,
            'cleaning_activities' => $employee->cleaningActivities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'name' => $activity->activity,
                    'status' => $activity->pivot->status,
                    'assigned_date' => $activity->pivot->assigned_date,
                    'completed_date' => $activity->pivot->completed_date,
                    'notes' => $activity->pivot->notes,
                ];
            }),
            'activities_count' => $employee->cleaning_activities_count,
        ];
    }

    /**
     * Obtiene un empleado específico con sus actividades
     * 
     * @param int $employeeId
     * @return array
     */
    public function getEmployeeWithActivities(int $employeeId): array
    {
        $employee = Employee::with([
            'cleaningActivities' => function ($query) {
                $query->withPivot(['status', 'assigned_date', 'completed_date', 'notes'])
                    ->orderBy('activity', 'asc');
            }
        ])
            ->withCount('cleaningActivities')
            ->findOrFail($employeeId);

        return $this->formatEmployeeData($employee);
    }

    /**
     * Obtiene estadísticas de asignaciones de actividades
     * 
     * @return array
     */
    public function getAssignmentStats(): array
    {
        $employees = Employee::where('is_active', true)->withCount('cleaningActivities')->get();

        $employeesWithActivities = $employees->filter(fn($emp) => $emp->cleaning_activities_count > 0);
        $employeesWithoutActivities = $employees->filter(fn($emp) => $emp->cleaning_activities_count === 0);

        // Estadísticas por estado
        $activitiesByStatus = Employee::where('is_active', true)->with([
            'cleaningActivities' => function ($query) {
                $query->withPivot('status');
            }
        ])->get()->flatMap(function ($employee) {
            return $employee->cleaningActivities;
        });

        $pendingCount = $activitiesByStatus->where('pivot.status', 'Pendiente')->count();
        $completedCount = $activitiesByStatus->where('pivot.status', 'Completada')->count();
        $cancelledCount = $activitiesByStatus->where('pivot.status', 'Cancelada')->count();

        return [
            'total_employees' => $employees->count(),
            'employees_with_activities' => $employeesWithActivities->count(),
            'employees_without_activities' => $employeesWithoutActivities->count(),
            'average_activities_per_employee' => round($employees->avg('cleaning_activities_count'), 2),
            'max_activities_assigned' => $employees->max('cleaning_activities_count'),
            'total_assignments' => $activitiesByStatus->count(),
            'activities_by_status' => [
                'pending' => $pendingCount,
                'completed' => $completedCount,
                'cancelled' => $cancelledCount,
            ],
        ];
    }

    /**
     * Obtiene actividades por estado para un empleado específico
     * 
     * @param int $employeeId
     * @return array
     */
    public function getActivitiesByStatus(int $employeeId): array
    {
        $employee = Employee::with([
            'cleaningActivities' => function ($query) {
                $query->withPivot('status');
            }
        ])->findOrFail($employeeId);

        $activities = $employee->cleaningActivities;

        return [
            'pending' => $activities->where('pivot.status', 'Pendiente')->count(),
            'completed' => $activities->where('pivot.status', 'Completada')->count(),
            'cancelled' => $activities->where('pivot.status', 'Cancelada')->count(),
        ];
    }

    /**
     * Obtiene las actividades del empleado logueado
     * 
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getMyActivities(array $data): LengthAwarePaginator
    {
        // Obtener el empleado del usuario logueado
        $user = auth()->user();
        $employee = $user->employee;

        if (!$employee) {
            throw new \Exception('No tienes un perfil de empleado asociado');
        }

        // Obtener actividades del empleado
        $query = $employee->cleaningActivities()
            ->withPivot(['status', 'assigned_date', 'completed_date', 'notes']);

        // Búsqueda por nombre de actividad
        if (!empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($q) use ($search) {
                $q->where('activity', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filtro por estado
        if (!empty($data['status'])) {
            $query->where('employee_cleaning_activity.status', $data['status']);
        }

        // Ordenamiento
        if (!empty($data['sortBy']) && !empty($data['orderBy'])) {
            $sortBy = $data['sortBy'];
            $orderBy = $data['orderBy'];

            if ($sortBy === 'activity_name') {
                $query->orderBy('activity', $orderBy);
            } elseif ($sortBy === 'assigned_date') {
                $query->orderBy('employee_cleaning_activity.assigned_date', $orderBy);
            } elseif ($sortBy === 'completed_date') {
                $query->orderBy('employee_cleaning_activity.completed_date', $orderBy);
            } elseif ($sortBy === 'status') {
                $query->orderBy('employee_cleaning_activity.status', $orderBy);
            } else {
                $query->orderBy($sortBy, $orderBy);
            }
        } else {
            $query->orderBy('employee_cleaning_activity.assigned_date', 'desc');
        }

        // Paginación
        $itemsPerPage = $data['itemsPerPage'] ?? 10;
        $activities = $query->paginate($itemsPerPage);

        // Transformar datos
        $activities->getCollection()->transform(function ($activity) {
            return [
                'activity_id' => $activity->id,
                'activity_name' => $activity->activity,
                'description' => $activity->description,
                'frequency' => $activity->frequency,
                'status' => $activity->pivot->status,
                'assigned_date' => $activity->pivot->assigned_date,
                'completed_date' => $activity->pivot->completed_date,
                'notes' => $activity->pivot->notes,
            ];
        });

        return $activities;
    }
    public function getMyExecutions(array $data): LengthAwarePaginator
    {
        // Obtener el empleado del usuario logueado
        $user = auth()->user();
        $employee = $user->employee;

        if (!$employee) {
            throw new \Exception('No tienes un perfil de empleado asociado');
        }

        // Obtener ejecuciones del empleado con la actividad relacionada
        $query = \App\Models\CleaningActivityExecution::with('cleaningActivity')
            ->where('employee_id', $employee->id);

        // Búsqueda por nombre de actividad
        if (!empty($data['q'])) {
            $search = $data['q'];
            $query->whereHas('cleaningActivity', function ($q) use ($search) {
                $q->where('activity', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filtro por estado
        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        // Ordenamiento
        if (!empty($data['sortBy']) && !empty($data['orderBy'])) {
            $sortBy = $data['sortBy'];
            $orderBy = $data['orderBy'];

            if ($sortBy === 'activity_name') {
                $query->join('cleaning_activities', 'cleaning_activity_executions.cleaning_activity_id', '=', 'cleaning_activities.id')
                    ->select('cleaning_activity_executions.*')
                    ->orderBy('cleaning_activities.activity', $orderBy);
            } elseif ($sortBy === 'scheduled_date') {
                $query->orderBy('scheduled_date', $orderBy);
            } elseif ($sortBy === 'due_date') { // NUEVO
                $query->orderBy('due_date', $orderBy);
            } elseif ($sortBy === 'completed_date') {
                $query->orderBy('completed_date', $orderBy);
            } elseif ($sortBy === 'status') {
                $query->orderBy('status', $orderBy);
            } else {
                $query->orderBy($sortBy, $orderBy);
            }
        } else {
            // Ordenar por fecha límite por defecto (más urgentes primero)
            $query->orderBy('due_date', 'asc');
        }

        // Paginación
        $itemsPerPage = $data['itemsPerPage'] ?? 10;
        $executions = $query->paginate($itemsPerPage);

        // Transformar datos
        $executions->getCollection()->transform(function ($execution) {
            return [
                'execution_id' => $execution->id,
                'activity_id' => $execution->cleaning_activity_id,
                'activity_name' => $execution->cleaningActivity->activity,
                'description' => $execution->cleaningActivity->description,
                'frequency' => $execution->cleaningActivity->frequency,
                'status' => $execution->status,
                'scheduled_date' => $execution->scheduled_date,
                'due_date' => $execution->due_date, // NUEVO
                'completed_date' => $execution->completed_date,
                'approved_date' => $execution->approved_date,
                'photo' => $execution->photo,
                'notes' => $execution->notes,
                'rejection_reason' => $execution->rejection_reason,
                // Información adicional útil
                'is_late' => $execution->isLate(), // NUEVO
                'days_until_due' => $execution->daysUntilDue(), // NUEVO
                'is_due_soon' => $execution->isDueSoon(), // NUEVO
            ];
        });

        return $executions;
    }
    public function getSupervisorExecutions(array $data): LengthAwarePaginator
    {
        // Obtener ejecuciones con empleado y actividad relacionados (solo empleados activos)
        $query = \App\Models\CleaningActivityExecution::with(['employee', 'cleaningActivity', 'approvedBy'])
            ->whereHas('employee', function($q) {
                $q->where('is_active', true);
            })
            ->whereIn('status', ['Procesada', 'Completada', 'Vencida', 'Cancelada']); // Mostrar procesadas y completadas

        // Búsqueda por nombre de empleado o actividad
        if (!empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', function ($empQuery) use ($search) {
                    $empQuery->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhereRaw("CONCAT(name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                })
                    ->orWhereHas('cleaningActivity', function ($actQuery) use ($search) {
                        $actQuery->where('activity', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Filtro por estado
        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        // Filtro por empleado
        if (!empty($data['employee_id'])) {
            $query->where('employee_id', $data['employee_id']);
        }

        // Filtro por rango de fechas
        if (!empty($data['date_from'])) {
            $query->whereDate('completed_date', '>=', $data['date_from']);
        }
        if (!empty($data['date_to'])) {
            $query->whereDate('completed_date', '<=', $data['date_to']);
        }

        // Ordenamiento
        if (!empty($data['sortBy']) && !empty($data['orderBy'])) {
            $sortBy = $data['sortBy'];
            $orderBy = $data['orderBy'];

            if ($sortBy === 'employee_name') {
                $query->join('employees', 'cleaning_activity_executions.employee_id', '=', 'employees.id')
                    ->select('cleaning_activity_executions.*')
                    ->orderBy('employees.name', $orderBy)
                    ->orderBy('employees.last_name', $orderBy);
            } elseif ($sortBy === 'activity_name') {
                $query->join('cleaning_activities', 'cleaning_activity_executions.cleaning_activity_id', '=', 'cleaning_activities.id')
                    ->select('cleaning_activity_executions.*')
                    ->orderBy('cleaning_activities.activity', $orderBy);
            } else {
                $query->orderBy($sortBy, $orderBy);
            }
        } else {
            // Por defecto: Procesadas primero, luego por fecha de completado
            $query->orderByRaw("FIELD(status, 'Procesada', 'Completada', 'Vencida', 'Cancelada')")
                ->orderBy('completed_date', 'desc');
        }

        // Paginación
        $itemsPerPage = $data['itemsPerPage'] ?? 10;
        $executions = $query->paginate($itemsPerPage);

        // Transformar datos
        $executions->getCollection()->transform(function ($execution) {
            return [
                'execution_id' => $execution->id,
                'employee_id' => $execution->employee_id,
                'employee_name' => $execution->employee ? trim($execution->employee->name . ' ' . $execution->employee->last_name) : 'N/A',
                'employee_photo' => $execution->employee ? $execution->employee->photo_url : null,
                'activity_id' => $execution->cleaning_activity_id,
                'activity_name' => $execution->cleaningActivity->activity,
                'description' => $execution->cleaningActivity->description,
                'frequency' => $execution->cleaningActivity->frequency,
                'status' => $execution->status,
                'scheduled_date' => $execution->scheduled_date,
                'due_date' => $execution->due_date,
                'completed_date' => $execution->completed_date,
                'approved_date' => $execution->approved_date,
                'approved_by' => $execution->approvedBy ? $execution->approvedBy->name : null,
                'photo' => $execution->photo,
                'notes' => $execution->notes,
                'rejection_reason' => $execution->rejection_reason,
            ];
        });

        return $executions;
    }

    /**
     * Obtiene estadísticas para el supervisor
     * 
     * @return array
     */
    public function getSupervisorStats(): array
    {
        $baseQuery = \App\Models\CleaningActivityExecution::whereHas('employee', function($q) {
            $q->where('is_active', true);
        });

        $pending = (clone $baseQuery)->where('status', 'Procesada')->count();
        $approved = (clone $baseQuery)->where('status', 'Completada')->count();
        $rejected = (clone $baseQuery)->whereNotNull('rejection_reason')->count();
        $overdue = (clone $baseQuery)->where('status', 'Vencida')->count();
        $cancelled = (clone $baseQuery)->where('status', 'Cancelada')->count();

        // Actividades procesadas hoy
        $todayProcessed = (clone $baseQuery)->where('status', 'Procesada')
            ->whereDate('completed_date', today())
            ->count();

        return [
            'pending_review' => $pending,
            'approved_total' => $approved,
            'rejected_total' => $rejected,
            'overdue_total' => $overdue,
            'cancelled_total' => $cancelled,
            'processed_today' => $todayProcessed,
        ];
    }

}
