<?php

namespace App\Contracts\Suppliers;

interface DrocercaScraperServiceInterface
{
    /**
     * Sincroniza las facturas desde el portal Drocerca con el ERP.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array;

    /**
     * Extrae el listado de documentos directamente de Drocerca.
     */
    public function fetchDocuments(string $username, string $password): array;

    /**
     * Extrae el estado de cuenta y efectos por pagar en las 3 sedes (Mérida, Centro, Oriente).
     */
    public function fetchEdoCuenta(string $username, string $password): array;
}

