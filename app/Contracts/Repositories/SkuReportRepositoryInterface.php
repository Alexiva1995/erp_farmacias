<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Builder;

interface SkuReportRepositoryInterface
{
    /**
     * Obtiene el query base para el reporte de SKU con los joins necesarios.
     *
     * @param array $filters
     * @return Builder
     */
    public function getBaseQuery(array $filters): Builder;

    /**
     * Obtiene los productos vencidos por SKU en el periodo.
     *
     * @param array $filters
     * @return \Illuminate\Support\Collection
     */
    public function getExpiredProducts(array $filters);

    /**
     * Obtiene las devoluciones de productos (mermas) por SKU en el periodo.
     *
     * @param array $filters
     * @return \Illuminate\Support\Collection
     */
    public function getReturnedProducts(array $filters);
}
