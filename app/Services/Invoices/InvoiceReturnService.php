<?php

namespace App\Services\Invoices;

use App\Contracts\Repositories\InvoiceReturnRepositoryInterface;
use App\Models\InvoiceReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceReturnService
{
    public function __construct(
        private InvoiceReturnRepositoryInterface $invoiceReturnRepository
    ) {
    }

    /**
     * Obtener listado de devoluciones paginado.
     */
    public function getReturns(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->invoiceReturnRepository->getPaginatedReturns($filters, $perPage);
    }

    /**
     * Actualizar estado de una devolución individual.
     */
    public function updateReturnStatus(int $returnId, string $status): InvoiceReturn
    {
        return $this->invoiceReturnRepository->updateStatus($returnId, $status);
    }

    /**
     * Actualizar estado masivo de devoluciones de una factura.
     */
    public function updateStatusByInvoice(int $invoiceId, string $status): int
    {
        return $this->invoiceReturnRepository->updateStatusByInvoice($invoiceId, $status);
    }
}
