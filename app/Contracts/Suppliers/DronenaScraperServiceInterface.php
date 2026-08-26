<?php

namespace App\Contracts\Suppliers;

interface DronenaScraperServiceInterface
{
    /**
     * Sincroniza las facturas desde el portal Dronena con el ERP.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array;

    /**
     * Extrae el listado de documentos directamente de Dronena.
     */
    public function fetchDocuments(string $username, string $password): array;
}
