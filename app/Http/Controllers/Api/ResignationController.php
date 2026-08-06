<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Contracts\Resignation as ResignationContract;
use App\Models\Employee;
use App\Http\Requests\Employee\GenerateResignationRequest;
use App\Http\Requests\Resignation\ToggleEmployeeStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResignationController extends Controller
{
    public function __construct(
        private ResignationContract $resignationServices
    ) {}

    public function generateResignation(GenerateResignationRequest $request)
    {
        try {
            $resignationData = $request->all();

            // Eliminada la restricción de fecha efectiva anterior a solicitud


            // Obtener email del usuario si no viene en request
            if (empty($resignationData['employee_email'])) {

                $employee = Employee::with('user')->find($resignationData['employee_id']);
                if ($employee && $employee->user) {
                    $resignationData['employee_email'] = $employee->user->email;
                } else {
                }
            }

            // Verificar si ya existe una renuncia para este empleado

            $existingResignation = $this->resignationServices->getByEmployeeId($resignationData['employee_id']);

            if ($existingResignation && !$request->get('is_edit', false)) {

                // Si existe y no es edición, retornar error para mostrar modal de confirmación
                return response()->json([
                    'error' => 'Ya existe una carta de renuncia para este empleado',
                    'message' => 'Este empleado ya tiene una carta de renuncia generada. ¿Desea editarla?',
                    'existing_resignation' => [
                        'id' => $existingResignation->id,
                        'employee_name' => $existingResignation->employee_name,
                        'resignation_type' => $existingResignation->resignation_type,
                        'effective_date' => $existingResignation->effective_date->format('Y-m-d'),
                        'request_date' => $existingResignation->request_date->format('Y-m-d'),
                    ]
                ], 409);
            }

            // Guardar o actualizar renuncia en base de datos
            if ($existingResignation && $request->get('is_edit', false)) {

                $resignation = $this->resignationServices->update($existingResignation->id, $resignationData);
            } else {

                $resignation = $this->resignationServices->store($resignationData);
            }

            // Generar PDF dinámicamente usando datos de la BD

            $pdf = $this->resignationServices->generatePdf($resignationData);

            // Notificar a Jesús Freita

            $this->resignationServices->notifyLiquidation($resignationData);

            // Retornar PDF para descarga (sin almacenar archivo)
            $filename = 'carta-renuncia-' . $resignationData['employee_identification'] . '.pdf';

            return $pdf->download($filename);
        } catch (\Illuminate\Validation\ValidationException $e) {

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

            $user = $request->user();
            if ($user && $user->role_id !== 1) {
                $employee = Employee::where('user_id', $user->id)->first();
                if ($employee) {
                    $filters['employee_id'] = $employee->id;
                } else {
                    $filters['employee_id'] = -1;
                }
            }

            $result = $this->resignationServices->list([
                'page' => $page,
                'perPage' => $perPage,
                ...$filters
            ]);

            return response()->json([
                'success' => true,
                'data' => \App\Http\Resources\ResignationResource::collection($result->items()),
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

            return response()->json([
                'error' => 'Error al obtener estadísticas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar estado del empleado (is_active)
     */
    public function toggleEmployeeStatus(ToggleEmployeeStatusRequest $request)
    {
        try {
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

            return response()->json([
                'error' => 'Error al generar PDF',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de una renuncia para edición por ID de empleado
     */
    public function getResignationForEditByEmployee(int $employeeId)
    {

        try {

            $resignation = $this->resignationServices->getByEmployeeId($employeeId);

            if (!$resignation) {

                return response()->json([
                    'error' => 'Renuncia no encontrada'
                ], 404);
            }

            $responseData = [
                'id' => $resignation->id,
                'employee_id' => $resignation->employee_id,
                'employee_name' => $resignation->employee_name,
                'employee_identification' => $resignation->employee_identification,
                'employee_email' => $resignation->employee_email,
                'employee_position' => $resignation->employee_position,
                'start_date' => $resignation->start_date->format('Y-m-d'),
                'resignation_type' => $resignation->resignation_type,
                'request_date' => $resignation->request_date->format('Y-m-d'),
                'effective_date' => $resignation->effective_date->format('Y-m-d'),
                'employee_status' => $resignation->employee_status,
                'created_at' => $resignation->created_at,
                'updated_at' => $resignation->updated_at
            ];

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error al obtener datos de la renuncia',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de renuncia existente para edición
     */
    public function getResignationForEdit(int $resignationId)
    {
        try {
            $resignation = $this->resignationServices->getById($resignationId);

            if (!$resignation) {
                return response()->json([
                    'error' => 'Renuncia no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $resignation->id,
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
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error al obtener datos de la renuncia',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar renuncia (soft delete)
     */
    public function deleteResignation(int $resignationId)
    {
        try {
            $resignation = $this->resignationServices->getById($resignationId);

            if (!$resignation) {
                return response()->json([
                    'error' => 'Renuncia no encontrada'
                ], 404);
            }

            $deleted = $this->resignationServices->delete($resignationId);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Renuncia eliminada exitosamente'
                ]);
            } else {
                return response()->json([
                    'error' => 'Error al eliminar la renuncia'
                ], 500);
            }
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error al eliminar la renuncia',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
