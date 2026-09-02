<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

interface DrosymcaScraperServiceInterface
{
    /**
     * Sincroniza las facturas y fechas de vencimiento desde el portal Drosymca con el ERP.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array;

    /**
     * Extrae el listado de facturas pendientes de la sección Cobranza de Drosymca.
     */
    public function fetchPendingInvoices(string $username, string $password): array;

    /**
     * Extrae el detalle de una factura específica en el portal de Drosymca.
     */
    public function fetchInvoiceDetail(string $invoiceUrlOrId, ?string $cookieFile = null, ?string $username = null, ?string $password = null): ?array;
}
