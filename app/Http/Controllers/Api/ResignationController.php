<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ResignationService;
use App\Services\ResignationStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResignationController extends Controller
{
    public function __construct(
        private ResignationService $resignationService,
        private ResignationStorageService $storageService
    ) {}

    public function generateResignation(Request $request)
    {
        try {
            Log::info('Resignation request received', $request->all());

            $request->validate([
                'employee_id' => 'required|integer',
                'employee_name' => 'required|string',
                'employee_identification' => 'required|string',
                'employee_position' => 'nullable|string',
                'start_date' => 'required|date',
                'resignation_type' => 'required|in:voluntary,unjustified_dismissal',
                'request_date' => 'required|date',
                'effective_date' => 'required|date|after_or_equal:today',
            ]);

            $resignationData = $request->all();
            Log::info('Validation passed, generating PDF', $resignationData);

            // Guardar renuncia en archivo JSON
            Log::info('Attempting to store resignation data', $resignationData);
            $stored = $this->storageService->storeResignation($resignationData);
            Log::info('Storage service result', ['stored' => $stored]);
            if (!$stored) {
                Log::error('Failed to store resignation data - possible duplicate');
                return response()->json([
                    'error' => 'Ya existe una renuncia para este empleado',
                    'message' => 'No se puede generar una nueva renuncia para un empleado que ya tiene una renuncia registrada'
                ], 409);
            }
            Log::info('Resignation data stored successfully');

            // Generar PDF
            $pdf = $this->resignationService->generatePdf($resignationData);
            Log::info('PDF generated successfully');

            // Notificar a Jesús Freita
            $this->resignationService->notifyLiquidation($resignationData);

            // Retornar PDF para descarga
            $filename = 'carta-renuncia-' . $resignationData['employee_identification'] . '.pdf';
            Log::info('Returning PDF download', ['filename' => $filename]);

            return $pdf->download($filename);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Error de validación',
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('PDF generation error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al generar la carta de renuncia',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Listar renuncias con paginación y filtros
     */
    public function listResignations(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('perPage', 10);
            $filters = $request->only(['search', 'resignation_type', 'date_from', 'date_to']);

            $result = $this->storageService->getResignationsPaginated($page, $perPage, $filters);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error listing resignations: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener la lista de renuncias',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de renuncias
     */
    public function getStats()
    {
        try {
            $stats = $this->storageService->getResignationStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting resignation stats: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener estadísticas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado del empleado (is_active)
     */
    public function toggleEmployeeStatus(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|integer|exists:employees,id',
                'is_active' => 'required|boolean'
            ]);

            $employee = \App\Models\Employee::findOrFail($request->employee_id);
            $employee->update(['is_active' => $request->is_active]);

            // Actualizar el estado en el archivo JSON de renuncias
            $this->updateEmployeeStatusInResignations($employee->id, $request->is_active);

            $status = $request->is_active ? 'activado' : 'desactivado';

            return response()->json([
                'success' => true,
                'message' => "Empleado {$status} exitosamente",
                'data' => [
                    'employee_id' => $employee->id,
                    'is_active' => $employee->is_active
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling employee status: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al cambiar el estado del empleado',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el estado del empleado en todas las renuncias del JSON
     */
    private function updateEmployeeStatusInResignations(int $employeeId, bool $isActive)
    {
        try {
            $resignations = $this->storageService->getAllResignations();
            $updated = false;

            foreach ($resignations as &$resignation) {
                if ($resignation['employee_id'] == $employeeId) {
                    $resignation['employee_status'] = $isActive ? 'Activo' : 'Inactivo';
                    $resignation['updated_at'] = now()->toISOString();
                    $updated = true;
                }
            }

            if ($updated) {
                $this->storageService->saveResignations($resignations);
                Log::info("Updated employee status in resignations JSON", [
                    'employee_id' => $employeeId,
                    'is_active' => $isActive
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating employee status in resignations: ' . $e->getMessage());
        }
    }
}
