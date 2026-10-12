<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

interface MafartaScraperServiceInterface
{
    /**
     * Sincroniza las facturas y estados de cuenta desde el portal SIC de Cobeca/Mafarta con el ERP.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null, ?string $sessionToken = null): array;

    /**
     * Extrae el listado de documentos pendientes/indexados directamente del portal SIC de Droguerías Cobeca.
     */
    public function fetchDocuments(string $username, string $password, ?string $sessionToken = null): array;

    /**
     * Valida si un token de sesión de Cobeca sigue activo consultando el estado de cuenta.
     */
    public function testSessionToken(string $token, ?int $clientCode = null): array;

    /**
     * Obtiene el detalle oficial de una factura con su número de control y renglones.
     */
    public function getInvoiceDetail(string $invoiceNumber, string $token): ?array;

    /**
     * Reporta y procesa un pago directamente en el portal SIC de Cobeca / Mafarta.
     */
    public function submitPayment(
        array $invoiceNumbers,
        float $paymentAmount,
        string $reference,
        string $destinationBank,
        string $paymentDate,
        ?string $receiptPath = null,
        string $idType = 'V',
        string $idNumber = '24150980'
    ): array;
}
