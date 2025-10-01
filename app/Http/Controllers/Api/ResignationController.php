<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Contracts\Resignation;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResignationController extends Controller
{
    public function __construct(
        private Resignation $resignationServices
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
                'effective_date' => 'required|date|after_or_equal:request_date',
            ]);

            $resignationData = $request->all();
            Log::info('Validation passed, generating PDF', $resignationData);

            // Obtener email del usuario si no viene en request
            if (empty($resignationData['employee_email'])) {
                $employee = Employee::with('user')->find($resignationData['employee_id']);
                if ($employee && $employee->user) {
                    $resignationData['employee_email'] = $employee->user->email;
                }
            }

            // Guardar renuncia en base de datos
            Log::info('Attempting to store resignation data in database', $resignationData);
            $resignation = $this->resignationServices->store($resignationData);
            Log::info('Resignation stored successfully', ['resignation_id' => $resignation->id]);

            // Generar PDF dinámicamente usando datos de la BD
            $pdf = $this->resignationServices->generatePdf($resignationData);
            Log::info('PDF generated successfully');

            // Notificar a Jesús Freita
            $this->resignationServices->notifyLiquidation($resignationData);

            // Retornar PDF para descarga (sin almacenar archivo)
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
            // Manejo de errores mejorado
            if (strpos($e->getMessage(), 'Ya existe una renuncia') !== false) {
                return response()->json([
                    'error' => 'Ya existe una renuncia para este empleado',
                    'message' => 'No se puede generar una nueva renuncia para un empleado que ya tiene una renuncia registrada'
                ], 409);
            }

            Log::error('PDF generation error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al generar la carta de renuncia',
                'message' => $e->getMessage()
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

            $result = $this->resignationServices->list([
                'page' => $page,
                'perPage' => $perPage,
                ...$filters
            ]);

            return response()->json([
                'success' => true,
                'data' => $result->items(),
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'from' => $result->firstItem(),
                    'to' => $result->lastItem()
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
            $stats = $this->resignationServices->getStats();

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

            $employee = Employee::findOrFail($request->employee_id);
            $employee->update(['is_active' => $request->is_active]);

            // Actualizar el estado en la tabla de renuncias
            $this->resignationServices->updateEmployeeStatus($employee->id, $request->is_active);

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
     * Descargar PDF de renuncia existente (generación dinámica)
     */
    public function downloadResignationPdf(int $resignationId)
    {
        try {
            $resignation = $this->resignationServices->getById($resignationId);

            if (!$resignation) {
                return response()->json([
                    'error' => 'Renuncia no encontrada'
                ], 404);
            }

            // Preparar datos para generación de PDF
            $resignationData = [
                'employee_id' => $resignation->employee_id,
                'employee_name' => $resignation->employee_name,
                'employee_identification' => $resignation->employee_identification,
                'employee_email' => $resignation->employee_email,
                'employee_position' => $resignation->employee_position,
                'start_date' => $resignation->start_date->format('Y-m-d'),
                'resignation_type' => $resignation->resignation_type,
                'request_date' => $resignation->request_date->format('Y-m-d'),
                'effective_date' => $resignation->effective_date->format('Y-m-d'),
                'employee_status' => $resignation->employee_status
            ];

            // Generar PDF dinámicamente
            $pdf = $this->resignationServices->generatePdf($resignationData);

            $filename = 'carta-renuncia-' . $resignation->employee_identification . '.pdf';
            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Error generating PDF for download: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar PDF',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
