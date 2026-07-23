<?php

declare(strict_types=1);

namespace App\Services\EmployeeLaboratories;

use App\Models\Employee;
use App\Models\Laboratory;
use Illuminate\Support\Facades\DB;

class EmployeeLaboratoryActionService
{
    /**
     * Asigna o actualiza laboratorios a un empleado
     * 
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function assignLaboratories(array $data): bool
    {
        $validated = $this->validateAssignmentData($data);

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($validated['employee_id']);

            // Sync reemplaza todas las asignaciones con las nuevas
            // Usa syncWithoutDetaching si deseas mantener las existentes
            $employee->laboratories()->sync($validated['laboratory_ids']);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al asignar laboratorios: ' . $e->getMessage());
        }
    }

    /**
     * Elimina una asignación específica de laboratorio a empleado
     * 
     * @param Employee $employee
     * @param int $laboratoryId
     * @return bool
     * @throws \Exception
     */
    public function removeAssignment(Employee $employee, int $laboratoryId): bool
    {
        try {
            DB::beginTransaction();

            // Verificar que el laboratorio existe
            $laboratory = Laboratory::findOrFail($laboratoryId);

            // Verificar que el empleado tiene ese laboratorio asignado
            if (!$employee->laboratories()->where('laboratories.id', $laboratoryId)->exists()) {
                throw new \Exception('El empleado no tiene asignado este laboratorio');
            }

            // Eliminar la relación
            $employee->laboratories()->detach($laboratoryId);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al eliminar la asignación: ' . $e->getMessage());
        }
    }

    /**
     * Agrega laboratorios adicionales sin reemplazar los existentes
     * 
     * @param int $employeeId
     * @param array $laboratoryIds
     * @return bool
     * @throws \Exception
     */
    public function addLaboratories(int $employeeId, array $laboratoryIds): bool
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);

            // Validar que todos los laboratorios existen
            foreach ($laboratoryIds as $labId) {
                Laboratory::findOrFail($labId);
            }

            // Agregar sin eliminar los existentes
            $employee->laboratories()->syncWithoutDetaching($laboratoryIds);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al agregar laboratorios: ' . $e->getMessage());
        }
    }

    /**
     * Elimina todos los laboratorios asignados a un empleado
     * 
     * @param Employee $employee
     * @return bool
     * @throws \Exception
     */
    public function removeAllLaboratories(Employee $employee): bool
    {
        try {
            DB::beginTransaction();

            $employee->laboratories()->detach();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al eliminar todos los laboratorios: ' . $e->getMessage());
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

        if (empty($data['laboratory_ids'])) {
            throw new \InvalidArgumentException('Debe proporcionar al menos un laboratorio');
        }

        if (!is_array($data['laboratory_ids'])) {
            throw new \InvalidArgumentException('Los laboratorios deben ser un array');
        }

        if (count($data['laboratory_ids']) === 0) {
            throw new \InvalidArgumentException('Debe seleccionar al menos un laboratorio');
        }

        // Validar que todos los IDs sean numéricos
        foreach ($data['laboratory_ids'] as $labId) {
            if (!is_numeric($labId) || $labId <= 0) {
                throw new \InvalidArgumentException('Todos los IDs de laboratorio deben ser números válidos');
            }
        }

        return [
            'employee_id' => (int) $data['employee_id'],
            'laboratory_ids' => array_map('intval', $data['laboratory_ids']),
        ];
    }
}
