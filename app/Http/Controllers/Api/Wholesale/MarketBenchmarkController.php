<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wholesale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wholesale\MarketBenchmarkFilterRequest;
use App\Http\Requests\Wholesale\QuickUpdatePriceRequest;
use App\Http\Resources\Wholesale\MarketBenchmarkItemResource;
use App\Services\Wholesale\WholesaleMarketBenchmarkService;
use Illuminate\Http\JsonResponse;

class MarketBenchmarkController extends Controller
{
    public function __construct(
        protected WholesaleMarketBenchmarkService $benchmarkService
    ) {}

    /**
     * Retorna los KPIs de posicionamiento de mercado y semáforo de competitividad.
     */
    public function getSummary(MarketBenchmarkFilterRequest $request): JsonResponse
    {
        $summary = $this->benchmarkService->getBenchmarkSummary($request->validated());

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Retorna el listado paginado y anónimo de productos por segmento (más baratos, más caros o sin cobertura).
     */
    public function getItems(MarketBenchmarkFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $segment = (string) ($validated['segment'] ?? 'cheaper');
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);

        $paginator = $this->benchmarkService->getPaginatedBenchmarkItems($segment, $validated, $perPage, $page);

        return response()->json([
            'success' => true,
            'data' => MarketBenchmarkItemResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Actualización rápida de precio mayorista para un producto propio desde el dashboard.
     */
    public function updatePrice(int $product, QuickUpdatePriceRequest $request): JsonResponse
    {
        $priceUsd = (float) $request->input('price_usd');
        $result = $this->benchmarkService->updateProductPrice($product, $priceUsd);

        return response()->json($result);
    }
}
