<?php

namespace App\Services\Suppliers;

use App\Contracts\Repositories\MarketOpportunityRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Clase MarketOpportunityService
 * 
 * Orquesta la obtención de datos de oportunidades de mercado y aplica
 * lógica de negocio adicional si es necesaria.
 */
class MarketOpportunityService
{
    /**
     * Constructor del servicio.
     *
     * @param MarketOpportunityRepositoryInterface $repository
     */
    public function __construct(
        protected MarketOpportunityRepositoryInterface $repository
    ) {
    }

    /**
     * Obtener el listado de oportunidades según los filtros.
     *
     * @param array $filtros
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getOpportunities(array $filtros, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->getPaginatedOpportunities($filtros, $perPage);
    }

    /**
     * Obtener todas las oportunidades sin paginación (útil para exportaciones).
     *
     * @param array $filtros
     * @return Collection
     */
    public function getAll(array $filtros): Collection
    {
        return $this->repository->getAllOpportunities($filtros);
    }
}
