<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

interface CristmedicalsScraperServiceInterface
{
    /**
     * Sincroniza las facturas, vencimientos, descuentos y montos en Bs desde el portal web de Cristmedicals con el ERP.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array;

    /**
     * Extrae el listado de facturas pendientes directamente del portal web de Cristmedicals.
     */
    public function fetchInvoices(string $username, string $password): array;

    /**
     * Reporta y procesa un pago directamente en el portal web de Cristmedicals.
     */
    public function submitPayment(
        array $invoiceNumbers,
        float $paymentAmount,
        string $reference,
        string $destinationBank = '30',
        ?string $paymentDate = null,
        string $paymentMethod = '2'
    ): array;
}