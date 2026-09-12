<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Chronic\ChronicClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChronicClientController extends Controller
{
    public function __construct(
        protected ChronicClientService $chronicService
    ) {
    }

    /**
     * Listar pacientes crónicos con filtros y paginación.
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->chronicService->getChronicPatients($request);

        return ApiResponse::success([
            'items' => $paginated->items(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ], 'Pacientes crónicos obtenidos exitosamente', 200);
    }

    /**
     * Obtener estadísticas de pacientes y tratamientos crónicos.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->chronicService->getStats();

        return ApiResponse::success($stats, 'Estadísticas obtenidas exitosamente', 200);
    }

    /**
     * Obtener catálogo de productos con configuración de consumo y frecuencia.
     */
    public function productsConfig(Request $request): JsonResponse
    {
        $paginated = $this->chronicService->getProductConsumptionConfig($request);

        return ApiResponse::success([
            'items' => $paginated->items(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ], 'Configuración de consumo de productos obtenida exitosamente', 200);
    }

    /**
     * Actualizar configuración de consumo y frecuencia de un producto.
     */
    public function updateProductConfig(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'consumption_type' => 'required|string|in:chronic,single_treatment,no_alert,sporadic',
            'treatment_duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        $product = $this->chronicService->updateProductConsumption($id, $validated);

        return ApiResponse::success($product, 'Configuración de producto actualizada exitosamente', 200);
    }

    /**
     * Ejecutar sincronización y detección de medicamentos crónicos mediante IA.
     */
    public function syncAi(): JsonResponse
    {
        $result = $this->chronicService->syncChronicProductsWithAi();

        return ApiResponse::success($result, 'Sincronización de productos crónicos con IA completada exitosamente', 200);
    }
}