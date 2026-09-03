<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface SupplierReturnsRepositoryInterface
{
    /**
     * Obtiene los lotes con stock > 0 que vencen dentro de los próximos N días,
     * con toda la información necesaria para generar la solicitud de canje preventivo.
     */
    public function getLotsExpiringSoon(array $filters, int $days = 90): array;
}
