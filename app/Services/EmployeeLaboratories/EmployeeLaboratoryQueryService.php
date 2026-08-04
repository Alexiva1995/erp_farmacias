<?php

declare(strict_types=1);

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
            ->select(['employees.id', 'employees.name', 'employees.last_name', 'employees.identification', 'employees.is_active', 'employees.photo'])
            ->with(['laboratories' => function ($q) {
                $q->select(['laboratories.id', 'laboratories.name']);
            }])
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
        return $query->paginate($itemsPerPage);
    }

    /**
     * Obtiene estadísticas de asignaciones de laboratorios
     * 
     * @return array
     */
    public function getAssignmentStats(): array
    {
        $stats = Employee::query()
            ->selectRaw('
                COUNT(*) as total_employees,
                COUNT(CASE WHEN (SELECT COUNT(*) FROM employee_laboratory WHERE employee_laboratory.employee_id = employees.id) > 0 THEN 1 END) as employees_with_laboratories,
                COUNT(CASE WHEN (SELECT COUNT(*) FROM employee_laboratory WHERE employee_laboratory.employee_id = employees.id) = 0 THEN 1 END) as employees_without_laboratories
            ')
            ->where('is_active', true)
            ->first();

        return [
            'total_employees' => (int) ($stats->total_employees ?? 0),
            'employees_with_laboratories' => (int) ($stats->employees_with_laboratories ?? 0),
            'employees_without_laboratories' => (int) ($stats->employees_without_laboratories ?? 0),
        ];
    }
}
