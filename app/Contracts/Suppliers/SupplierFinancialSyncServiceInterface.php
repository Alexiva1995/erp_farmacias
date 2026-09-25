<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use App\Models\Supplier;

interface SupplierFinancialSyncServiceInterface
{
    /**
     * Identifica y ejecuta el scraper financiero correspondiente para enriquecer facturas y vencimientos.
     */
    public function syncSupplierFinancials(Supplier|int $supplier, ?string $onlyInvoice = null): array;

    /**
     * Despacha el Job asíncrono para ejecutar el scraper financiero en segundo plano.
     */
    public function dispatchFinancialSync(Supplier|int $supplier, ?string $onlyInvoice = null): void;

    /**
     * Determina si el proveedor tiene un scraper financiero configurado o soportado.
     */
    public function hasFinancialScraper(Supplier|int $supplier): bool;
}
