<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

interface MafartaScraperServiceInterface
{
    /**
     * Sincroniza las facturas y estados de cuenta desde el portal SIC de Cobeca/Mafarta con el ERP.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array;

    /**
     * Extrae el listado de documentos pendientes/indexados directamente del portal SIC de Droguerías Cobeca.
     */
    public function fetchDocuments(string $username, string $password): array;

    /**
     * Obtiene el detalle oficial de una factura con su número de control y renglones.
     */
    public function getInvoiceDetail(string $invoiceNumber, string $token): ?array;
}
