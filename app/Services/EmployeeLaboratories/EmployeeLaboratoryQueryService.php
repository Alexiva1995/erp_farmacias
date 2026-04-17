<?php

namespace App\Services\EmployeeLaboratories;

use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

class EmployeeLaboratoryQueryService
{
    /**
     * Obtiene una consulta filtrada de empleados con sus laboratorios
     * 
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getFilteredEmployeeLaboratories(array $data): LengthAwarePaginator
    {
        $query = Employee::query()
            ->with(['laboratories'])
            ->withCount('laboratories')
            ->where('employees.is_active', true);

        // Búsqueda por nombre de empleado
        if (!empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Filtro por laboratorio específico
        if (!empty($data['laboratory_id'])) {
            $query->whereHas('laboratories', function ($q) use ($data) {
                $q->where('laboratories.id', $data['laboratory_id']);
            });
        }

        // Ordenamiento prioritario por foto (quienes tienen foto primero)
        $query->orderByRaw('CASE WHEN photo IS NOT NULL AND photo != "" AND photo != "null" THEN 0 ELSE 1 END');

        // Ordenamiento especificado o por nombre por defecto
        if (!empty($data['sortBy']) && !empty($data['orderBy'])) {
            $sortBy = $data['sortBy'];
            $orderBy = $data['orderBy'];

            if ($sortBy === 'employee_name') {
                $query->orderBy('name', $orderBy);
            } elseif ($sortBy === 'laboratories_count') {
                $query->orderBy('laboratories_count', $orderBy);
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
            return [
                'employee_id' => $employee->id,
                'employee_name' => trim($employee->name . ' ' . $employee->last_name),
                'identification' => $employee->identification,
                'is_active' => $employee->is_active,
                'laboratories' => $employee->laboratories->map(function ($lab) {
                    return [
                        'id' => $lab->id,
                        'name' => $lab->name,
                    ];
                }),
                'laboratories_count' => $employee->laboratories_count,
                'photo_url' => $employee->photo_url,
            ];
        });

        return $employees;
    }

    /**
     * Obtiene estadísticas de asignaciones de laboratorios
     * 
     * @return array
     */
    public function getAssignmentStats(): array
    {
        $employees = Employee::withCount('laboratories')->get();

        $employeesWithLabs = $employees->filter(fn($emp) => $emp->laboratories_count > 0);
        $employeesWithoutLabs = $employees->filter(fn($emp) => $emp->laboratories_count === 0);

        return [
            'total_employees' => $employees->count(),
            'employees_with_laboratories' => $employeesWithLabs->count(),
            'employees_without_laboratories' => $employeesWithoutLabs->count(),
            'average_laboratories_per_employee' => $employees->avg('laboratories_count'),
            'max_laboratories_assigned' => $employees->max('laboratories_count'),
        ];
    }
}
