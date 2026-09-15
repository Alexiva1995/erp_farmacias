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
                    $q->select(['products.id', 'products.name', 'products.laboratory_id'])
                        ->with(['laboratory:id,name']);
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

        // Transformar datos para el frontend y calcular ventas desde la fecha de asignación
        $employeesCollection = $employees->getCollection();

        // 1. Recopilar IDs de usuarios y productos/platos a consultar
        $employeeUserMap = [];
        $productPairs = []; // [ ['seller_id' => x, 'product_id' => y, 'assigned_at' => z], ... ]
        $dishPairs = [];

        foreach ($employeesCollection as $employee) {
            if (!$employee->user_id) {
                continue;
            }
            $sellerId = $employee->user_id;

            foreach ($employee->products as $prod) {
                $assignedAt = $prod->pivot->created_at ?? null;
                $productPairs[] = [
                    'seller_id' => $sellerId,
                    'product_id' => $prod->id,
                    'assigned_at' => $assignedAt ? $assignedAt->toDateTimeString() : '2000-01-01 00:00:00',
                ];
            }

            foreach ($employee->dishes as $dish) {
                $assignedAt = $dish->pivot->created_at ?? null;
                $dishPairs[] = [
                    'seller_id' => $sellerId,
                    'dish_id' => $dish->id,
                    'assigned_at' => $assignedAt ? $assignedAt->toDateTimeString() : '2000-01-01 00:00:00',
                ];
            }
        }

        // 2. Consultar ventas agrupadas de productos en una sola consulta
        $productSalesMap = [];
        if (!empty($productPairs)) {
            $sellerIds = array_unique(array_column($productPairs, 'seller_id'));
            $productIds = array_unique(array_column($productPairs, 'product_id'));

            $salesQuery = \Illuminate\Support\Facades\DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereIn('orders.seller_id', $sellerIds)
                ->whereIn('order_details.product_id', $productIds)
                ->where('orders.status', 'Completed')
                ->select([
                    'orders.seller_id',
                    'order_details.product_id',
                    'orders.order_date',
                    'orders.created_at',
                    'order_details.quantity',
                ])
                ->get();

            // Asignar ventas según fecha de asignación de cada par
            foreach ($productPairs as $pair) {
                $key = "{$pair['seller_id']}_{$pair['product_id']}";
                $assignedAt = $pair['assigned_at'];

                $qty = $salesQuery->filter(function ($row) use ($pair, $assignedAt) {
                    $orderTime = $row->order_date ?? $row->created_at;
                    return (int)$row->seller_id === (int)$pair['seller_id']
                        && (int)$row->product_id === (int)$pair['product_id']
                        && $orderTime >= $assignedAt;
                })->sum('quantity');

                $productSalesMap[$key] = (float)$qty;
            }
        }

        // 3. Consultar ventas agrupadas de platos en una sola consulta
        $dishSalesMap = [];
        if (!empty($dishPairs)) {
            $sellerIds = array_unique(array_column($dishPairs, 'seller_id'));
            $dishIds = array_unique(array_column($dishPairs, 'dish_id'));

            $dishSalesQuery = \Illuminate\Support\Facades\DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereIn('orders.seller_id', $sellerIds)
                ->whereIn('order_details.dish_id', $dishIds)
                ->where('orders.status', 'Completed')
                ->select([
                    'orders.seller_id',
                    'order_details.dish_id',
                    'orders.order_date',
                    'orders.created_at',
                    'order_details.quantity',
                ])
                ->get();

            foreach ($dishPairs as $pair) {
                $key = "{$pair['seller_id']}_{$pair['dish_id']}";
                $assignedAt = $pair['assigned_at'];

                $qty = $dishSalesQuery->filter(function ($row) use ($pair, $assignedAt) {
                    $orderTime = $row->order_date ?? $row->created_at;
                    return (int)$row->seller_id === (int)$pair['seller_id']
                        && (int)$row->dish_id === (int)$pair['dish_id']
                        && $orderTime >= $assignedAt;
                })->sum('quantity');

                $dishSalesMap[$key] = (float)$qty;
            }
        }

        // 4. Mapear datos con las unidades vendidas
        $employees->getCollection()->transform(function ($employee) use ($productSalesMap, $dishSalesMap) {
            $sellerId = $employee->user_id;

            $products = $employee->products->map(function ($prod) use ($sellerId, $productSalesMap) {
                $salesCount = 0;
                if ($sellerId) {
                    $key = "{$sellerId}_{$prod->id}";
                    $salesCount = $productSalesMap[$key] ?? 0;
                }

                return [
                    'id' => $prod->id,
                    'name' => $prod->name,
                    'laboratory_name' => $prod->laboratory?->name ?? null,
                    'sales_count' => $salesCount,
                    'assigned_at' => $prod->pivot->created_at?->format('Y-m-d H:i') ?? null,
                    'type' => 'product',
                ];
            });

            $dishes = $employee->dishes->map(function ($dish) use ($sellerId, $dishSalesMap) {
                $salesCount = 0;
                if ($sellerId) {
                    $key = "{$sellerId}_{$dish->id}";
                    $salesCount = $dishSalesMap[$key] ?? 0;
                }

                return [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'laboratory_name' => null,
                    'sales_count' => $salesCount,
                    'assigned_at' => $dish->pivot->created_at?->format('Y-m-d H:i') ?? null,
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
