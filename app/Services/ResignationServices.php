<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Resignation;
use App\Models\Resignation as MResignation;
use App\Repositories\ResignationRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ResignationServices implements Resignation
{
    public function __construct(
        protected ResignationRepository $resignationRepository
    ) {}

    /**
     * Listar renuncias con paginación y filtros
     */
    public function list(array $data): LengthAwarePaginator
    {
        $page = $data['page'] ?? 1;
        $perPage = $data['perPage'] ?? 10;
        $filters = array_intersect_key($data, array_flip(['search', 'resignation_type', 'date_from', 'date_to', 'employee_id']));

        $result = $this->resignationRepository->getResignationsPaginated($page, $perPage, $filters);

        // Convertir a LengthAwarePaginator para mantener consistencia con el patrón
        return new LengthAwarePaginator(
            $result['data'],
            $result['total'],
            $result['per_page'],
            $result['current_page'],
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Guardar una nueva renuncia
     */
    public function store(array $data): MResignation
    {
        return $this->resignationRepository->storeResignation($data);
    }

    /**
     * Obtener estadísticas de renuncias
     */
    public function getStats(): array
    {
        return $this->resignationRepository->getResignationStats();
    }

    /**
     * Actualizar estado del empleado en la renuncia
     */
    public function updateEmployeeStatus(int $employeeId, bool $isActive): bool
    {
        return $this->resignationRepository->updateEmployeeStatus($employeeId, $isActive);
    }

    /**
     * Obtener renuncia por ID
     */
    public function getById(int $id): ?MResignation
    {
        return $this->resignationRepository->getResignationById($id);
    }

    /**
     * Obtener renuncia por employee_id
     */
    public function getByEmployeeId(int $employeeId): ?MResignation
    {
        Log::info('🔍 [SERVICE] getByEmployeeId llamado', ['employee_id' => $employeeId]);
        $result = $this->resignationRepository->getResignationByEmployeeId($employeeId);
        Log::info('🔍 [SERVICE] Resultado de getByEmployeeId:', [
            'found' => $result ? 'yes' : 'no',
            'resignation_id' => $result ? $result->id : null
        ]);

        return $result;
    }

    /**
     * Actualizar una renuncia existente
     */
    public function update(int $id, array $data): MResignation
    {
        Log::info('🔄 [SERVICE] Actualizando renuncia', ['resignation_id' => $id, 'data' => $data]);
        $result = $this->resignationRepository->updateResignation($id, $data);
        if ($result) {
            Log::info('✅ [SERVICE] Renuncia actualizada exitosamente', ['resignation_id' => $id]);
            return $this->resignationRepository->getResignationById($id);
        }

        Log::error('❌ [SERVICE] No se pudo actualizar la renuncia', ['resignation_id' => $id]);
        throw new \Exception('No se pudo actualizar la renuncia');
    }

    /**
     * Eliminar una renuncia
     */
    public function delete(int $id): bool
    {
        return $this->resignationRepository->deleteResignation($id);
    }

    /**
     * Verificar si existe renuncia para un empleado
     */
    public function hasResignation(int $employeeId): bool
    {
        return $this->resignationRepository->hasResignation($employeeId);
    }

    /**
     * Obtener renuncias por período
     */
    public function getByPeriod(string $startDate, string $endDate): Collection
    {
        return $this->resignationRepository->getResignationsByPeriod($startDate, $endDate);
    }

    // ========================================
    // MÉTODOS DE GENERACIÓN DE PDF
    // ========================================

    /**
     * Preparar datos para generación de PDF
     */
    public function generatePdfData($resignationData): array
    {
        $id = $resignationData['employee_identification'];
        // Limpiar identificación y aplicar formato V- con puntos de mil
        $numericId = preg_replace('/[^0-9]/', '', $id);
        $formattedId = 'V-' . number_format((int)$numericId, 0, ',', '.');

        return [
            'employee_name' => $resignationData['employee_name'],
            'employee_identification' => $formattedId,
            'employee_position' => $resignationData['employee_position'] ?? 'empleado',
            'start_date_formatted' => $this->formatDate($resignationData['start_date']),
            'effective_date_formatted' => $this->formatDate($resignationData['effective_date']),
        ];
    }

    /**
     * Generar PDF de carta de renuncia
     */
    public function generatePdf($resignationData)
    {
        $pdfData = $this->generatePdfData($resignationData);

        $pdf = Pdf::loadView('pdf.resignation-letter', $pdfData);
        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }

    /**
     * Descargar PDF de carta de renuncia
     */
    public function downloadPdf($resignationData)
    {
        $pdf = $this->generatePdf($resignationData);
        $filename = 'carta-renuncia-' . $resignationData['employee_identification'] . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Formatear fecha para PDF
     */
    private function formatDate($date)
    {
        return Carbon::parse($date)->format('d/m/Y');
    }

    /**
     * Notificar a liquidación sobre nueva renuncia
     */
    public function notifyLiquidation($resignationData): void
    {
        // TODO: Implementar notificación a Jesús Freita
        // Por ahora solo log
        \Illuminate\Support\Facades\Log::info('Notificación de liquidación enviada', $resignationData);
    }
}
