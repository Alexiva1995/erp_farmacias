<?php

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

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al asignar productos: ' . $e->getMessage());
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

            // Verificar que el producto existe
            $product = Product::findOrFail($productId);

            // Verificar que el empleado tiene ese producto asignado
            if (!$employee->products()->where('products.id', $productId)->exists()) {
                throw new \Exception('El empleado no tiene asignado este producto');
            }

            // Eliminar la relación
            $employee->products()->detach($productId);

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

        if (empty($data['product_ids'])) {
            throw new \InvalidArgumentException('Debe proporcionar al menos un producto');
        }

        if (!is_array($data['product_ids'])) {
            throw new \InvalidArgumentException('Los productos deben ser un array');
        }

        if (count($data['product_ids']) === 0) {
            throw new \InvalidArgumentException('Debe seleccionar al menos un producto');
        }

        // Validar que todos los IDs sean numéricos
        foreach ($data['product_ids'] as $prodId) {
            if (!is_numeric($prodId) || $prodId <= 0) {
                throw new \InvalidArgumentException('Todos los IDs de producto deben ser números válidos');
            }
        }

        return [
            'employee_id' => (int) $data['employee_id'],
            'product_ids' => array_map('intval', $data['product_ids']),
        ];
    }
}
