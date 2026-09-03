<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Suppliers\PurchaseOrderByLaboratoryServiceInterface;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\PurchaseOrderByLaboratoryRequest;
use App\Http\Resources\PurchaseOrderByLaboratoryDetailResource;
use App\Http\Resources\PurchaseOrderByLaboratoryResource;
use Illuminate\Http\JsonResponse;

class PurchaseOrderByLaboratoryController extends Controller
{
    public function __construct(
        protected PurchaseOrderByLaboratoryServiceInterface $purchaseOrderByLaboratoryService
    ) {
    }

    /**
     * Obtiene el listado consolidado de órdenes agrupadas por laboratorio.
     */
    public function getLaboratories(PurchaseOrderByLaboratoryRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $paginated = $this->purchaseOrderByLaboratoryService->getAggregatedLaboratories($filters);

        return ApiResponse::success([
            'data' => PurchaseOrderByLaboratoryResource::collection($paginated->items()),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    /**
     * Obtiene los KPIs estadísticos de órdenes por laboratorio.
     */
    public function getStats(PurchaseOrderByLaboratoryRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $stats = $this->purchaseOrderByLaboratoryService->getStats($filters);

        return ApiResponse::success($stats);
    }

    /**
     * Obtiene el listado de productos pedidos de un laboratorio específico.
     */
    public function getDetails(PurchaseOrderByLaboratoryRequest $request, string|int $laboratoryId): JsonResponse
    {
        $filters = $request->validated();
        $paginated = $this->purchaseOrderByLaboratoryService->getLaboratoryDetails($laboratoryId, $filters);

        return ApiResponse::success([
            'data' => PurchaseOrderByLaboratoryDetailResource::collection($paginated->items()),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    /**
     * Exporta los detalles de un laboratorio.
     */
    public function getExportData(PurchaseOrderByLaboratoryRequest $request, string|int $laboratoryId): JsonResponse
    {
        $filters = $request->validated();
        $data = $this->purchaseOrderByLaboratoryService->getExportData($laboratoryId, $filters);

        return ApiResponse::success(
            PurchaseOrderByLaboratoryDetailResource::collection($data)
        );
    }
}
