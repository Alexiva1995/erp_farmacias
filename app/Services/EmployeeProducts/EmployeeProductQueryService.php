<?php

namespace App\Services\EmployeeProducts;

use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

class EmployeeProductQueryService
{
    /**
     * Obtiene una consulta filtrada de empleados con sus productos
     * 
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function getFilteredEmployeeProducts(array $data): LengthAwarePaginator
    {
        $query = Employee::where('is_active', true)
            ->with(['products'])
            ->withCount('products');

        // Búsqueda por nombre de empleado
        if (!empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Filtro por producto específico
        if (!empty($data['product_id'])) {
            $query->whereHas('products', function ($q) use ($data) {
                $q->where('products.id', $data['product_id']);
            });
        }

        // Ordenamiento
        if (!empty($data['sortBy']) && !empty($data['orderBy'])) {
            $sortBy = $data['sortBy'];
            $orderBy = $data['orderBy'];

            if ($sortBy === 'employee_name') {
                $query->orderBy('name', $orderBy);
            } elseif ($sortBy === 'products_count') {
                $query->orderBy('products_count', $orderBy);
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
                'products' => $employee->products->map(function ($prod) {
                    return [
                        'id' => $prod->id,
                        'name' => $prod->name,
                    ];
                }),
                'products_count' => $employee->products_count,
            ];
        });

        return $employees;
    }

    /**
     * Obtiene estadísticas de asignaciones de productos
     * 
     * @return array
     */
    public function getAssignmentStats(): array
    {
        $employees = Employee::where('is_active', true)->withCount('products')->get();

        $employeesWithProds = $employees->filter(fn($emp) => $emp->products_count > 0);
        $employeesWithoutProds = $employees->filter(fn($emp) => $emp->products_count === 0);

        return [
            'total_employees' => $employees->count(),
            'employees_with_products' => $employeesWithProds->count(),
            'employees_without_products' => $employeesWithoutProds->count(),
            'average_products_per_employee' => $employees->avg('products_count'),
            'max_products_assigned' => $employees->max('products_count'),
        ];
    }
}
