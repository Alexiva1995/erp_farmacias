<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\SupplierReturnsRequest;
use App\Services\Bi\SupplierReturnsService;
use Illuminate\Http\JsonResponse;

class SupplierReturnsController extends Controller
{
    public function __construct(
        protected SupplierReturnsService $service
    ) {}

    /**
     * Devuelve los lotes que vencen en los próximos 90 días agrupados por laboratorio.
     * Incluye totales por grupo y resumen global para KPI cards y carta de canje en PDF.
     */
    public function index(SupplierReturnsRequest $request): JsonResponse
    {
        $data = $this->service->getReport(
            $request->validated(),
            days: 90
        );

        return response()->json($data);
    }
}
