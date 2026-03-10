<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repository\MarketOpportunityRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketOpportunityController extends Controller
{
    public function __construct(
        private MarketOpportunityRepository $marketOpportunityRepository
    ) {}

    /**
     * Lista las oportunidades de mercado detectadas.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['q', 'laboratoryId', 'productId', 'sortBy', 'orderBy']);
        $perPage = $request->input('itemsPerPage', 10);

        $opportunities = $this->marketOpportunityRepository->getPaginatedOpportunities($filtros, $perPage);

        return response()->json([
            'data' => $opportunities->items(),
            'total' => $opportunities->total(),
        ]);
    }

    /**
     * Exporta todas las oportunidades (opcional, para uso futuro).
     */
    public function export(Request $request): JsonResponse
    {
        $filtros = $request->only(['q', 'laboratoryId', 'productId', 'sortBy', 'orderBy']);
        $opportunities = $this->marketOpportunityRepository->getAllOpportunities($filtros);

        return response()->json(['data' => $opportunities]);
    }
}
