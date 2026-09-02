<?php

namespace App\Contracts\Suppliers;

interface DromegaScraperServiceInterface
{
    /**
     * Extrae las facturas directamente del estado de cuenta de Droguería Mega.
     */
    public function fetchInvoices(?string $cookie = null, ?string $user = null, ?string $pass = null): array;

    /**
     * Sincroniza las facturas de Droguería Mega en la base de datos del ERP.
     */
    public function syncInvoices(?string $cookie = null, ?string $username = null, ?string $password = null, ?int $supplierId = null): array;

    /**
     * Reporta y procesa un pago directamente en el portal web de Droguería Mega.
     */
    public function submitPayment(
        array $invoiceNumbers,
        float $paymentAmount,
        string $reference,
        string $destinationBank = 'C1051',
        ?string $paymentDate = null,
        ?string $photoUrl = null
    ): array;

    /**
     * Extrae el detalle de renglones/productos de una factura desde el portal de Droguería Mega.
     */
    public function fetchInvoiceDetail(string $invoiceNumber, ?string $cookie = null): ?array;

    /**
     * Genera y almacena el archivo PDF digital de la factura para visualizarla en el ERP.
     */
    public function generateAndStoreInvoicePdf(\App\Models\Invoice $invoice, ?array $detailData = null): ?string;
}
