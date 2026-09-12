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
        protected ChronicClientService \
    ) {
    }

    /**
     * Listar pacientes crónicos con filtros y paginación.
     */
    public function index(Request \): JsonResponse
    {
        \ = \->chronicService->getChronicPatients(\);

        return ApiResponse::success([
            'items' => \->items(),
            'total' => \->total(),
            'current_page' => \->currentPage(),
            'per_page' => \->perPage(),
            'last_page' => \->lastPage(),
        ], 'Pacientes crónicos obtenidos exitosamente', 200);
    }

    /**
     * Obtener estadísticas de pacientes y tratamientos crónicos.
     */
    public function stats(): JsonResponse
    {
        \ = \->chronicService->getStats();

        return ApiResponse::success(\, 'Estadísticas obtenidas exitosamente', 200);
    }

    /**
     * Ejecutar sincronización y detección de medicamentos crónicos mediante IA.
     */
    public function syncAi(): JsonResponse
    {
        \ = \->chronicService->syncChronicProductsWithAi();

        return ApiResponse::success(\, 'Sincronización de productos crónicos con IA completada exitosamente', 200);
    }
}
