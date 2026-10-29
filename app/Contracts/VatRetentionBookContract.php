<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\VatRetentionBook;
use App\Models\VatRetentionBookEntry;
use Illuminate\Pagination\LengthAwarePaginator;

interface VatRetentionBookContract
{
    /**
     * Obtiene el listado paginado de reportes de retención de IVA con filtros.
     */
    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtiene o genera el reporte de retenciones para un período específico.
     */
    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): VatRetentionBook;

    /**
     * Obtiene un reporte por ID con sus renglones y relaciones.
     */
    public function getBookById(int $id): VatRetentionBook;

    /**
     * Sincroniza y recalcula los comprobantes y facturas de retención de un período abierto.
     */
    public function syncTransactions(VatRetentionBook $book): VatRetentionBook;

    /**
     * Actualiza un renglón de retención específico.
     */
    public function updateEntry(VatRetentionBook $book, int $entryId, array $data): VatRetentionBookEntry;

    /**
     * Cierra el reporte de retenciones bloqueando su edición.
     */
    public function closeBook(VatRetentionBook $book, int $userId): VatRetentionBook;

    /**
     * Reabre un reporte previamente cerrado.
     */
    public function reopenBook(VatRetentionBook $book): VatRetentionBook;
}
