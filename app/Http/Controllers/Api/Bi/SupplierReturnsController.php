<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\SupplierReturnsRequest;
use App\Http\Resources\Bi\SupplierReturnsReportResource;
use App\Services\Bi\SupplierReturnsService;
use Illuminate\Http\JsonResponse;

class SupplierReturnsController extends Controller
{
    public function __construct(
        protected SupplierReturnsService $service
    ) {}

    /**
     * Devuelve los lotes que vencen en los próximos 90 días agrupados por laboratorio.
     * La respuesta pasa por SupplierReturnsReportResource para garantizar tipos
     * estrictos y evitar serializar columnas innecesarias al frontend.
     */
    public function index(SupplierReturnsRequest $request): JsonResponse
    {
        $data = $this->service->getReport(
            $request->validated(),
            days: 90
        );

        return (new SupplierReturnsReportResource($data))
            ->response()
            ->setStatusCode(200);
    }
}
