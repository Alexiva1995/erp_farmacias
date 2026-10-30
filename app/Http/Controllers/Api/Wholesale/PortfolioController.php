<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wholesale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wholesale\AssignPortfolioRequest;
use App\Http\Requests\Wholesale\UpdateClientWholesaleTermsRequest;
use App\Models\Client;
use App\Services\Wholesale\WholesalePortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function __construct(
        protected WholesalePortfolioService $portfolioService
    ) {}

    /**
     * Listado paginado de clientes de cartera.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'sales_rep_id',
            'telemarketing_user_id',
            'collector_user_id',
            'delivery_route_id',
            'search',
            'has_debt',
            'is_overdue',
        ]);

        $perPage = (int) $request->input('per_page', 25);
        $clients = $this->portfolioService->getPortfolioClients($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Métricas y KPIs de la cartera.
     */
    public function metrics(Request $request): JsonResponse
    {
        $userId = $request->filled('user_id') ? (int) $request->user_id : null;
        $roleType = $request->filled('role_type') ? (string) $request->role_type : null;

        $metrics = $this->portfolioService->getPortfolioMetrics($userId, $roleType);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Asignación masiva de cartera a usuarios/rutas.
     */
    public function assign(AssignPortfolioRequest $request): JsonResponse
    {
        $assignments = $request->only([
            'sales_rep_id',
            'telemarketing_user_id',
            'collector_user_id',
            'delivery_route_id',
        ]);

        $updatedCount = $this->portfolioService->assignPortfolio($request->client_ids, $assignments);

        return response()->json([
            'success' => true,
            'message' => "Se actualizaron con éxito {$updatedCount} clientes de la cartera.",
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Actualiza cupos de crédito, días plazo, topes mensuales y permisos sanitarios SACS/SICM de una farmacia.
     */
    public function updateClientTerms(int $clientId, UpdateClientWholesaleTermsRequest $request): JsonResponse
    {
        $client = Client::findOrFail($clientId);
        $client->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Condiciones comerciales y de crédito actualizadas correctamente.',
            'data'    => $client,
        ]);
    }

    /**
     * Cálculo de comisiones sobre cobranzas para Asesor Comercial.
     */
    public function commissions(Request $request): JsonResponse
    {
        $request->validate([
            'sales_rep_id' => 'required|integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'commission_pct' => 'nullable|numeric|min:0|max:100',
        ]);

        $commissions = $this->portfolioService->calculateSalesRepCommissions(
            (int) $request->sales_rep_id,
            (string) $request->start_date,
            (string) $request->end_date,
            (float) ($request->commission_pct ?? 2.0)
        );

        return response()->json([
            'success' => true,
            'data' => $commissions,
        ]);
    }
}
