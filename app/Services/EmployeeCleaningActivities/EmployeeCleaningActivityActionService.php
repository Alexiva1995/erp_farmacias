<?php

namespace App\Services\EmployeeCleaningActivities;

use App\Models\CleaningActivity;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class EmployeeCleaningActivityActionService
{
    /**
     * Asigna o actualiza actividades de limpieza a un empleado
     * 
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function assignActivities(array $data): bool
    {
        $validated = $this->validateAssignmentData($data);

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($validated['employee_id']);

            // Preparar datos para sync con información adicional del pivot
            $syncData = [];
            foreach ($validated['activities'] as $activity) {
                $syncData[$activity['activity_id']] = [
                    'status' => 'Pendiente', // Forzamos pendiente siempre al asignar
                    'assigned_date' => $activity['assigned_date'] ?? now(),
                    'completed_date' => null,
                    'notes' => $activity['notes'] ?? null,
                    'day_of_week' => $activity['day_of_week'] ?? null,
                ];
            }

            // Sync reemplaza todas las asignaciones con las nuevas
            $employee->cleaningActivities()->sync($syncData);

            // Disparar generación automática de ejecuciones (calendario)
            // Llamamos al comando programáticamente para este empleado
            \Illuminate\Support\Facades\Artisan::call('cleaning:generate-executions', [
                '--days' => 30, // Generar un mes por adelantado
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al asignar actividades: ' . $e->getMessage());
        }
    }

    /**
     * Elimina una asignación específica de actividad a empleado
     * 
     * @param Employee $employee
     * @param int $activityId
     * @return bool
     * @throws \Exception
     */
    public function removeAssignment(Employee $employee, int $activityId): bool
    {
        try {
            DB::beginTransaction();

            // Verificar que la actividad existe
            $activity = CleaningActivity::findOrFail($activityId);

            // Verificar que el empleado tiene esa actividad asignada
            if (!$employee->cleaningActivities()->where('cleaning_activities.id', $activityId)->exists()) {
                throw new \Exception('El empleado no tiene asignada esta actividad');
            }

            // Eliminar la relación
            $employee->cleaningActivities()->detach($activityId);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al eliminar la asignación: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza el estado de una actividad asignada
     * 
     * @param int $employeeId
     * @param int $activityId
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function updateActivityStatus(int $employeeId, int $activityId, array $data): bool
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);

            // Verificar que la actividad está asignada
            if (!$employee->cleaningActivities()->where('cleaning_activities.id', $activityId)->exists()) {
                throw new \Exception('La actividad no está asignada a este empleado');
            }

            $updateData = [
                'status' => $data['status'],
                'updated_at' => now(),
            ];

            // Si el estado es completada, agregar fecha de completado
            if ($data['status'] === 'Completada') {
                $updateData['completed_date'] = $data['completed_date'] ?? now();
            }

            // Agregar notas si existen
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }

            $employee->cleaningActivities()->updateExistingPivot($activityId, $updateData);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al actualizar el estado: ' . $e->getMessage());
        }
    }

    /**
     * Agrega actividades adicionales sin reemplazar las existentes
     * 
     * @param int $employeeId
     * @param array $activities
     * @return bool
     * @throws \Exception
     */
    public function addActivities(int $employeeId, array $activities): bool
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);

            // Preparar datos para syncWithoutDetaching
            $syncData = [];
            foreach ($activities as $activity) {
                CleaningActivity::findOrFail($activity['activity_id']); // Validar existencia

                $syncData[$activity['activity_id']] = [
                    'status' => $activity['status'] ?? 'Pendiente',
                    'assigned_date' => $activity['assigned_date'] ?? now(),
                    'completed_date' => $activity['completed_date'] ?? null,
                    'notes' => $activity['notes'] ?? null,
                    'day_of_week' => $activity['day_of_week'] ?? null,
                ];
            }

            // Agregar sin eliminar las existentes
            $employee->cleaningActivities()->syncWithoutDetaching($syncData);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al agregar actividades: ' . $e->getMessage());
        }
    }

    /**
     * Elimina todas las actividades asignadas a un empleado
     * 
     * @param Employee $employee
     * @return bool
     * @throws \Exception
     */
    public function removeAllActivities(Employee $employee): bool
    {
        try {
            DB::beginTransaction();

            $employee->cleaningActivities()->detach();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al eliminar todas las actividades: ' . $e->getMessage());
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

        if (empty($data['activities'])) {
            throw new \InvalidArgumentException('Debe proporcionar al menos una actividad');
        }

        if (!is_array($data['activities'])) {
            throw new \InvalidArgumentException('Las actividades deben ser un array');
        }

        if (count($data['activities']) === 0) {
            throw new \InvalidArgumentException('Debe seleccionar al menos una actividad');
        }

        // Validar cada actividad
        foreach ($data['activities'] as $activity) {
            if (empty($activity['activity_id'])) {
                throw new \InvalidArgumentException('El ID de la actividad es requerido');
            }

            if (!is_numeric($activity['activity_id']) || $activity['activity_id'] <= 0) {
                throw new \InvalidArgumentException('El ID de la actividad debe ser un número válido');
            }

            if (empty($activity['status'])) {
                throw new \InvalidArgumentException('El estado de la actividad es requerido');
            }

            if (!in_array($activity['status'], ['Pendiente', 'Completada', 'Cancelada'])) {
                throw new \InvalidArgumentException('El estado debe ser: Pendiente, Completada o Cancelada');
            }
        }

        return $data;
    }
    public function updateExecutionStatus(int $employeeId, int $executionId, array $data, $photo = null): bool
    {
        try {
            DB::beginTransaction();

            $execution = \App\Models\CleaningActivityExecution::where('id', $executionId)
                ->where('employee_id', $employeeId)
                ->firstOrFail();

            // Verificar que la ejecución esté pendiente
            if (!$execution->isPending()) {
                throw new \Exception('Solo se pueden procesar ejecuciones en estado Pendiente');
            }

            $updateData = [
                'status' => $data['status'],
                'updated_at' => now(),
            ];

            // Si el estado es Procesada, manejar la foto
            if ($data['status'] === 'Procesada') {
                if (!$photo) {
                    throw new \Exception('La foto es requerida para marcar como procesada');
                }

                // Guardar la foto
                $photoPath = $photo->store('cleaning_activities_photos', 'public');
                $updateData['photo'] = $photoPath;
                $updateData['completed_date'] = now();
            }

            // Agregar notas si existen
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }

            $execution->update($updateData);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al actualizar el estado: ' . $e->getMessage());
        }
    }
    public function approveExecution(int $executionId, int $supervisorId, ?string $notes = null): bool
    {
        try {
            DB::beginTransaction();

            $execution = \App\Models\CleaningActivityExecution::findOrFail($executionId);

            // Verificar que esté en estado Procesada
            if (!$execution->isProcessed()) {
                throw new \Exception('Solo se pueden aprobar ejecuciones en estado Procesada');
            }

            $execution->update([
                'status' => 'Completada',
                'approved_by' => $supervisorId,
                'approved_date' => now(),
                'notes' => $notes ?? $execution->notes,
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al aprobar la ejecución: ' . $e->getMessage());
        }
    }

    /**
     * Rechaza una ejecución y la devuelve a Pendiente (supervisor)
     * 
     * @param int $executionId
     * @param int $supervisorId
     * @param string $rejectionReason
     * @return bool
     * @throws \Exception
     */
    public function rejectExecution(int $executionId, int $supervisorId, string $rejectionReason): bool
    {
        try {
            DB::beginTransaction();

            $execution = \App\Models\CleaningActivityExecution::findOrFail($executionId);

            // Verificar que esté en estado Procesada
            if (!$execution->isProcessed()) {
                throw new \Exception('Solo se pueden rechazar ejecuciones en estado Procesada');
            }

            // Eliminar la foto anterior si existe
            if ($execution->photo) {
                \Storage::disk('public')->delete($execution->photo);
            }

            $execution->update([
                'status' => 'Pendiente',
                'rejection_reason' => $rejectionReason,
                'completed_date' => null,
                'photo' => null,
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al rechazar la ejecución: ' . $e->getMessage());
        }
    }

    /**
     * Cancela una ejecución (supervisor)
     * 
     * @param int $executionId
     * @param int $supervisorId
     * @param string $cancellationReason
     * @return bool
     * @throws \Exception
     */
    public function cancelExecution(int $executionId, int $supervisorId, string $cancellationReason): bool
    {
        try {
            DB::beginTransaction();

            $execution = \App\Models\CleaningActivityExecution::findOrFail($executionId);

            // Solo se pueden cancelar ejecuciones Procesadas o Pendientes
            if (!in_array($execution->status, ['Pendiente', 'Procesada'])) {
                throw new \Exception('Solo se pueden cancelar ejecuciones Pendientes o Procesadas');
            }

            $execution->update([
                'status' => 'Cancelada',
                'rejection_reason' => $cancellationReason,
                'approved_by' => $supervisorId,
                'approved_date' => now(),
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error al cancelar la ejecución: ' . $e->getMessage());
        }
    }
}
