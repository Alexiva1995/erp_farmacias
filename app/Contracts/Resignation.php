<?php

namespace App\Contracts;

use App\Models\Resignation as MResignation;
use Illuminate\Pagination\LengthAwarePaginator;

interface Resignation
{
    /**
     * Listar renuncias con paginación y filtros
     */
    public function list(array $data): LengthAwarePaginator;

    /**
     * Guardar una nueva renuncia
     */
    public function store(array $data): MResignation;

    /**
     * Obtener estadísticas de renuncias
     */
    public function getStats(): array;

    /**
     * Actualizar estado del empleado en la renuncia
     */
    public function updateEmployeeStatus(int $employeeId, bool $isActive): bool;

    /**
     * Obtener renuncia por ID
     */
    public function getById(int $id): ?MResignation;

    /**
     * Obtener renuncia por employee_id
     */
    public function getByEmployeeId(int $employeeId): ?MResignation;

    /**
     * Actualizar una renuncia existente
     */
    public function update(int $id, array $data): bool;

    /**
     * Eliminar una renuncia
     */
    public function delete(int $id): bool;

    /**
     * Verificar si existe renuncia para un empleado
     */
    public function hasResignation(int $employeeId): bool;

    /**
     * Obtener renuncias por período
     */
    public function getByPeriod(string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection;

    // ========================================
    // MÉTODOS DE GENERACIÓN DE PDF
    // ========================================

    /**
     * Preparar datos para generación de PDF
     */
    public function generatePdfData($resignationData): array;

    /**
     * Generar PDF de carta de renuncia
     */
    public function generatePdf($resignationData);

    /**
     * Descargar PDF de carta de renuncia
     */
    public function downloadPdf($resignationData);

    /**
     * Notificar a liquidación sobre nueva renuncia
     */
    public function notifyLiquidation($resignationData): void;
}
