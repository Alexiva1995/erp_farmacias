<?php

declare(strict_types=1);

namespace AppHttpControllersApi;

use AppHelpersApiResponse;
use AppHttpControllersController;
use AppServicesChronicChronicClientService;
use IlluminateHttpJsonResponse;
use IlluminateHttpRequest;

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
