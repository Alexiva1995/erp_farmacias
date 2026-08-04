<?php

declare(strict_types=1);

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
                    $query->withPivot(['status', 'assigned_date', 'completed_date', 'notes', 'day_of_week'])
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
     * Obtiene una lista plana de todas las asignaciones
     * 
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getAllAssignments(array $data): LengthAwarePaginator
    {
        $query = \Illuminate\Support\Facades\DB::table('employee_cleaning_activity')
            ->join('employees', 'employee_cleaning_activity.employee_id', '=', 'employees.id')
            ->join('cleaning_activities', 'employee_cleaning_activity.cleaning_activity_id', '=', 'cleaning_activities.id')
            ->select(
                'employee_cleaning_activity.*',
                'employees.name as employee_name',
                'employees.last_name as employee_last_name',
                'cleaning_activities.activity as activity_name',
                'cleaning_activities.frequency as frequency'
            )
            ->where('employees.is_active', true);

        // Búsqueda
        if (!empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($q) use ($search) {
                $q->where('employees.name', 'LIKE', "%{$search}%")
                    ->orWhere('employees.last_name', 'LIKE', "%{$search}%")
                    ->orWhere('cleaning_activities.activity', 'LIKE', "%{$search}%");
            });
        }

        // Filtro Frecuencia
        if (!empty($data['frequency'])) {
            $query->where('cleaning_activities.frequency', $data['frequency']);
        }
        
        // Filtro Ocultar Diarias
        if (isset($data['hide_daily']) && ($data['hide_daily'] === 'true' || $data['hide_daily'] === true)) {
            $query->where('cleaning_activities.frequency', '!=', 'Diaria');
        }

        // Ordenamiento
        $query->orderBy('cleaning_activities.activity', 'asc');

        // Paginación
        $itemsPerPage = $data['itemsPerPage'] ?? 10;
        return $query->paginate($itemsPerPage);
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
                    'day_of_week' => $activity->pivot->day_of_week,
                    'frequency' => $activity->frequency,
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
                $query->withPivot(['status', 'assigned_date', 'completed_date', 'notes', 'day_of_week'])
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
        $totalEmployees = Employee::where('is_active', true)->count();
        
        $employeesWithActivities = Employee::where('is_active', true)
            ->has('cleaningActivities')
            ->count();

        $employeesWithoutActivities = max(0, $totalEmployees - $employeesWithActivities);

        $statusCounts = \Illuminate\Support\Facades\DB::table('employee_cleaning_activity')
            ->join('employees', 'employee_cleaning_activity.employee_id', '=', 'employees.id')
            ->where('employees.is_active', true)
            ->select('employee_cleaning_activity.status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('employee_cleaning_activity.status')
            ->pluck('count', 'status');

        $pendingCount = $statusCounts->get('Pendiente', 0);
        $completedCount = $statusCounts->get('Completada', 0);
        $cancelledCount = $statusCounts->get('Cancelada', 0);
        $totalAssignments = $statusCounts->sum();

        $maxAssigned = \Illuminate\Support\Facades\DB::table('employee_cleaning_activity')
            ->join('employees', 'employee_cleaning_activity.employee_id', '=', 'employees.id')
            ->where('employees.is_active', true)
            ->select('employee_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('employee_id')
            ->orderByDesc('total')
            ->value('total') ?? 0;

        $avgAssigned = $totalEmployees > 0 ? round($totalAssignments / $totalEmployees, 2) : 0;

        return [
            'total_employees' => $totalEmployees,
            'employees_with_activities' => $employeesWithActivities,
            'employees_without_activities' => $employeesWithoutActivities,
            'average_activities_per_employee' => $avgAssigned,
            'max_activities_assigned' => (int) $maxAssigned,
            'total_assignments' => $totalAssignments,
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
            ->withPivot(['status', 'assigned_date', 'completed_date', 'notes', 'day_of_week']);

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
                'day_of_week' => $activity->pivot->day_of_week,
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

        // Obtener solo la ejecución más reciente por actividad
        $subQuery = \App\Models\CleaningActivityExecution::where('employee_id', $employee->id)
            ->selectRaw('MAX(id) as id')
            ->groupBy('cleaning_activity_id');

        $query = \App\Models\CleaningActivityExecution::with('cleaningActivity')
            ->whereIn('id', $subQuery);

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
        $query = \App\Models\CleaningActivityExecution::with([
            'employee:id,name,last_name,photo,user_id',
            'cleaningActivity:id,activity,description,frequency',
            'approvedBy:id,name'
        ])
        ->whereHas('employee', function($q) {
            $q->where('is_active', true);
        });

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
        } else {
            $query->whereIn('status', ['Procesada', 'Vencida']);
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
                'activity_name' => $execution->cleaningActivity ? $execution->cleaningActivity->activity : 'N/A',
                'description' => $execution->cleaningActivity ? $execution->cleaningActivity->description : null,
                'frequency' => $execution->cleaningActivity ? $execution->cleaningActivity->frequency : null,
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
