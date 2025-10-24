<?php

namespace App\Repository;

use App\Models\Resignation;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ResignationRepository
{
    /**
     * Guardar una nueva renuncia en la base de datos
     */
    public function storeResignation(array $data): Resignation
    {
        // Validar que no existe renuncia activa para este empleado (excluyendo soft deletes)
        $existing = Resignation::where('employee_id', $data['employee_id'])->first();
        if ($existing) {
            throw new \Exception('Ya existe una renuncia para este empleado');
        }

        return Resignation::create($data);
    }

    /**
     * Obtener todas las renuncias con relaciones
     */
    public function getAllResignations(): Collection
    {
        return Resignation::with('employee')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Obtener renuncias paginadas con filtros
     */
    public function getResignationsPaginated(int $page, int $perPage, array $filters = []): array
    {
        $query = Resignation::with('employee');

        // Aplicar filtros
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['resignation_type'])) {
            $query->byType($filters['resignation_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('effective_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('effective_date', '<=', $filters['date_to']);
        }

        $result = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $result->items(),
            'total' => $result->total(),
            'per_page' => $result->perPage(),
            'current_page' => $result->currentPage(),
            'last_page' => $result->lastPage(),
            'from' => $result->firstItem(),
            'to' => $result->lastItem()
        ];
    }

    /**
     * Obtener estadísticas de renuncias
     */
    public function getResignationStats(): array
    {
        $total = Resignation::count();
        $voluntary = Resignation::byType('voluntary')->count();
        $unjustified = Resignation::byType('unjustified_dismissal')->count();
        $active = Resignation::byEmployeeStatus('Activo')->count();
        $inactive = Resignation::byEmployeeStatus('Inactivo')->count();

        $currentMonth = date('Y-m');
        $currentYear = date('Y');

        $thisMonth = Resignation::whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$currentMonth])->count();
        $thisYear = Resignation::whereRaw("DATE_FORMAT(created_at, '%Y') = ?", [$currentYear])->count();

        return [
            'total' => $total,
            'voluntary' => $voluntary,
            'unjustified_dismissal' => $unjustified,
            'active' => $active,
            'inactive' => $inactive,
            'this_month' => $thisMonth,
            'this_year' => $thisYear
        ];
    }

    /**
     * Actualizar estado del empleado en la renuncia
     */
    public function updateEmployeeStatus(int $employeeId, bool $isActive): bool
    {
        $resignation = Resignation::where('employee_id', $employeeId)->first();
        if ($resignation) {
            $resignation->update([
                'employee_status' => $isActive ? 'Activo' : 'Inactivo'
            ]);
            Log::info("Updated employee status in resignation", [
                'employee_id' => $employeeId,
                'is_active' => $isActive,
                'resignation_id' => $resignation->id
            ]);
            return true;
        }
        return false;
    }

    /**
     * Obtener renuncia por ID con relaciones
     */
    public function getResignationById(int $id): ?Resignation
    {
        return Resignation::with('employee')->find($id);
    }

    /**
     * Obtener renuncia por employee_id
     */
    public function getResignationByEmployeeId(int $employeeId): ?Resignation
    {
        $result = Resignation::where('employee_id', $employeeId)->first();

        return $result;
    }

    /**
     * Actualizar una renuncia existente
     */
    public function updateResignation(int $id, array $data): bool
    {
        $resignation = Resignation::find($id);
        if ($resignation) {
            // Solo actualizar campos específicos, mantener request_date original
            $updateData = [
                'employee_position' => $data['employee_position'] ?? $resignation->employee_position,
                'resignation_type' => $data['resignation_type'] ?? $resignation->resignation_type,
                'effective_date' => $data['effective_date'] ?? $resignation->effective_date,
                // No incluir request_date para mantener el valor original
                // updated_at se actualiza automáticamente por Laravel
            ];

            $result = $resignation->update($updateData);

            return true;
        }

        return false;
    }

    /**
     * Eliminar una renuncia
     */
    public function deleteResignation(int $id): bool
    {
        $resignation = Resignation::find($id);
        if ($resignation) {
            $resignation->delete();
            return true;
        }
        return false;
    }

    /**
     * Verificar si existe renuncia para un empleado
     */
    public function hasResignation(int $employeeId): bool
    {
        return Resignation::where('employee_id', $employeeId)->exists();
    }

    /**
     * Obtener renuncias por período
     */
    public function getResignationsByPeriod(string $startDate, string $endDate): Collection
    {
        return Resignation::whereBetween('effective_date', [$startDate, $endDate])
            ->with('employee')
            ->orderBy('effective_date', 'desc')
            ->get();
    }
}
