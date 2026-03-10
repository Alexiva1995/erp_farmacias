<?php

namespace App\Contracts\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface MarketOpportunityRepositoryInterface
 * @package App\Contracts\Repositories
 */
interface MarketOpportunityRepositoryInterface
{
    /**
     * Obtener oportunidades de mercado paginadas.
     *
     * @param array $filtros
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedOpportunities(array $filtros, int $perPage = 10): LengthAwarePaginator;

    /**
     * Obtener todas las oportunidades de mercado sin paginar.
     *
     * @param array $filtros
     * @return Collection
     */
    public function getAllOpportunities(array $filtros): Collection;
}
