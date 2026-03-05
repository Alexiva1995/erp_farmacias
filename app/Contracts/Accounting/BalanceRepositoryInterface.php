<?php

namespace App\Contracts\Accounting;

interface BalanceRepositoryInterface
{
    /**
     * Obtiene los activos brutos (Efectivo, Inventario, Mobiliario)
     */
    public function getAssets(): array;

    /**
     * Obtiene los pasivos (Deudas Proveedores, Préstamos)
     */
    public function getLiabilities(): array;

    /**
     * Obtiene la depreciación acumulada (Contra-activo)
     */
    public function getDepreciation(): float;
}
