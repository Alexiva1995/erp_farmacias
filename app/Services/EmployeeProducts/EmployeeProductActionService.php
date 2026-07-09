<?php

declare(strict_types=1);

namespace App\Services\EmployeeProducts;

use App\Models\Employee;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class EmployeeProductActionService
{
    /**
     * Asigna o actualiza productos a un empleado
     * 
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function assignProducts(array $data): bool
    {
        $validated = $this->validateAssignmentData($data);

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($validated['employee_id']);

            // Sync reemplaza todas las asignaciones con las nuevas
            $employee->products()->sync($validated['product_ids']);
            $employee->dishes()->sync($validated['dish_ids']);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al asignar productos o platos: ' . $e->getMessage());
        }
    }

    /**
     * Elimina una asignación específica de producto a empleado
     * 
     * @param Employee $employee
     * @param int $productId
     * @return bool
     * @throws \Exception
     */
    public function removeAssignment(Employee $employee, int $productId): bool
    {
        try {
            DB::beginTransaction();

            // Verificar si es producto o plato
            if ($employee->products()->where('products.id', $productId)->exists()) {
                $employee->products()->detach($productId);
            } elseif ($employee->dishes()->where('dishes.id', $productId)->exists()) {
                $employee->dishes()->detach($productId);
            } else {
                throw new \Exception('El empleado no tiene asignado este producto o plato');
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al eliminar la asignación: ' . $e->getMessage());
        }
    }

    /**
     * Agrega productos adicionales sin reemplazar los existentes
     * 
     * @param int $employeeId
     * @param array $productIds
     * @return bool
     * @throws \Exception
     */
    public function addProducts(int $employeeId, array $productIds): bool
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);

            // Validar que todos los productos existen
            foreach ($productIds as $prodId) {
                Product::findOrFail($prodId);
            }

            // Agregar sin eliminar los existentes
            $employee->products()->syncWithoutDetaching($productIds);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al agregar productos: ' . $e->getMessage());
        }
    }

    /**
     * Elimina todos los productos asignados a un empleado
     * 
     * @param Employee $employee
     * @return bool
     * @throws \Exception
     */
    public function removeAllProducts(Employee $employee): bool
    {
        try {
            DB::beginTransaction();

            $employee->products()->detach();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al eliminar todos los productos: ' . $e->getMessage());
        }
    }

    /**
     * Valida los datos de asignación
     * 
     * @param array $data
     * @return array
     * @throws \InvalidArgumentException
     */
    private function validateAssignmentData(array $data): array
    {
        if (empty($data['employee_id'])) {
            throw new \InvalidArgumentException('El ID del empleado es requerido');
        }

        if (!is_numeric($data['employee_id']) || $data['employee_id'] <= 0) {
            throw new \InvalidArgumentException('El ID del empleado debe ser un número válido');
        }

        $productIds = [];
        if (isset($data['product_ids']) && is_array($data['product_ids'])) {
            foreach ($data['product_ids'] as $prodId) {
                if (is_numeric($prodId) && $prodId > 0) {
                    $productIds[] = (int) $prodId;
                }
            }
        }

        $dishIds = [];
        if (isset($data['dish_ids']) && is_array($data['dish_ids'])) {
            foreach ($data['dish_ids'] as $dishId) {
                if (is_numeric($dishId) && $dishId > 0) {
                    $dishIds[] = (int) $dishId;
                }
            }
        }

        if (empty($productIds) && empty($dishIds)) {
            throw new \InvalidArgumentException('Debe seleccionar al menos un producto o plato');
        }

        return [
            'employee_id' => (int) $data['employee_id'],
            'product_ids' => $productIds,
            'dish_ids' => $dishIds,
        ];
    }
}
