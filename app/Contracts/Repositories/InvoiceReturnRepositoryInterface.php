<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use App\Models\InvoiceReturn;

interface InvoiceReturnRepositoryInterface
{
    /**
     * Obtener devoluciones de facturas con ordenación y filtros.
     * Devoluciones pendientes primero (de más antigua a más reciente), seguidas de aprobadas/rechazadas.
     */
    public function getPaginatedReturns(array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Actualizar estado de una devolución específica.
     */
    public function updateStatus(int $returnId, string $status): InvoiceReturn;

    /**
     * Actualizar estado masivo de devoluciones asociadas a una factura.
     */
    public function updateStatusByInvoice(int $invoiceId, string $status): int;
}
