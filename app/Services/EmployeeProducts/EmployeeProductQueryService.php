<?php

declare(strict_types=1);

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
        $query = Employee::query()
            ->select(['id', 'name', 'last_name', 'photo', 'identification', 'is_active'])
            ->where('is_active', true)
            ->with([
                'products' => function ($q) {
                    $q->select(['products.id', 'products.name']);
                },
                'dishes' => function ($q) {
                    $q->select(['dishes.id', 'dishes.name']);
                }
            ])
            ->withCount(['products', 'dishes'])
            ->orderByRaw('photo IS NOT NULL DESC');

        // Búsqueda por nombre de empleado
        if (!empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Filtro por producto o plato específico
        if (!empty($data['product_id'])) {
            $query->where(function ($q) use ($data) {
                $q->whereHas('products', function ($sp) use ($data) {
                    $sp->where('products.id', $data['product_id']);
                })->orWhereHas('dishes', function ($sd) use ($data) {
                    $sd->where('dishes.id', $data['product_id']);
                });
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
        $itemsPerPage = isset($data['itemsPerPage']) ? (int)$data['itemsPerPage'] : 10;
        $employees = $query->paginate($itemsPerPage);

        // Transformar datos para el frontend
        $employees->getCollection()->transform(function ($employee) {
            $products = $employee->products->map(function ($prod) {
                return [
                    'id' => $prod->id,
                    'name' => $prod->name,
                    'type' => 'product',
                ];
            });

            $dishes = $employee->dishes->map(function ($dish) {
                return [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'type' => 'dish',
                ];
            });

            $combined = $products->concat($dishes);

            return [
                'employee_id' => $employee->id,
                'employee_name' => trim($employee->name . ' ' . $employee->last_name),
                'photo_url' => $employee->photo_url,
                'identification' => $employee->identification,
                'is_active' => $employee->is_active,
                'products' => $combined,
                'products_count' => (int) ($employee->products_count + $employee->dishes_count),
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
        $employees = Employee::where('is_active', true)->withCount(['products', 'dishes'])->get();

        $employeesWithProds = $employees->filter(fn($emp) => ($emp->products_count + $emp->dishes_count) > 0);
        $employeesWithoutProds = $employees->filter(fn($emp) => ($emp->products_count + $emp->dishes_count) === 0);

        return [
            'total_employees' => $employees->count(),
            'employees_with_products' => $employeesWithProds->count(),
            'employees_without_products' => $employeesWithoutProds->count(),
            'average_products_per_employee' => $employees->avg(fn($emp) => $emp->products_count + $emp->dishes_count),
            'max_products_assigned' => $employees->max(fn($emp) => $emp->products_count + $emp->dishes_count),
        ];
    }
}
