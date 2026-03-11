<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\MarketOpportunityRequest;
use App\Http\Resources\MarketOpportunityResource;
use App\Services\Suppliers\MarketOpportunityService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Clase MarketOpportunityController
 * 
 * Gestiona los endpoints relacionados con las oportunidades de mercado detectadas por la IA.
 * Sigue el patrón de controlador delgado, delegando la lógica al servicio.
 */
class MarketOpportunityController extends Controller
{
    /**
     * Constructor del controlador.
     *
     * @param MarketOpportunityService $service
     */
    public function __construct(
        protected MarketOpportunityService $service
    ) {
    }

    /**
     * Listar las oportunidades de mercado detectadas con paginación y filtros.
     *
     * @param MarketOpportunityRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(MarketOpportunityRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->input('itemsPerPage', 10);
        $opportunities = $this->service->getOpportunities($request->all(), $perPage);

        return MarketOpportunityResource::collection($opportunities);
    }

    /**
     * Exportar todas las oportunidades de mercado detectadas sin paginación.
     *
     * @param MarketOpportunityRequest $request
     * @return AnonymousResourceCollection
     */
    public function export(MarketOpportunityRequest $request): AnonymousResourceCollection
    {
        $opportunities = $this->service->getAll($request->all());

        return MarketOpportunityResource::collection($opportunities);
    }
}
