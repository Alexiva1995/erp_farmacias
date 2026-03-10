<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

/**
 * Interface AbcReportRepositoryInterface
 * @package App\Contracts\Repositories
 */
interface AbcReportRepositoryInterface
{
    /**
     * Obtener los datos agregados para el cálculo ABC (Ventas, Márgenes y Rotación).
     *
     * @param array $filtros Filtros opcionales (fechas, laboratorio, etc.)
     * @return Collection
     */
    public function getAggregatedData(array $filtros): Collection;
}
