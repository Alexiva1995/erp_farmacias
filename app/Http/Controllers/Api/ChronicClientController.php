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
            "items" => $paginated->items(),
            "total" => $paginated->total(),
            "current_page" => $paginated->currentPage(),
            "per_page" => $paginated->perPage(),
            "last_page" => $paginated->lastPage(),
        ], "Pacientes crónicos obtenidos exitosamente", 200);
    }

    /**
     * Obtener estadísticas de pacientes y tratamientos crónicos.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->chronicService->getStats();

        return ApiResponse::success($stats, "Estadísticas obtenidas exitosamente", 200);
    }
}
