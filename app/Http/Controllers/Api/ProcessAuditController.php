<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcessAuditRequest;
use App\Http\Requests\StoreProcessFlowRequest;
use App\Services\ProcessAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessAuditController extends Controller
{
    public function __construct(private ProcessAuditService $processAuditService)
    {
    }

    /**
     * Obtener listado de auditorías.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'perPage' => $request->integer('itemsPerPage', 10),
            'startDate' => $request->input('startDate'),
            'endDate' => $request->input('endDate'),
            'flow_id' => $request->input('flow_id'),
        ];

        $results = $this->processAuditService->index($filters);

        return response()->json([
            'success' => true,
            'data' => $results->items(),
            'total' => $results->total(),
        ]);
    }

    /**
     * Almacenar una nueva auditoría.
     */
    public function store(StoreProcessAuditRequest $request): JsonResponse
    {
        $audit = $this->processAuditService->store($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Auditoría de proceso registrada con éxito.',
            'data' => $audit,
        ], 201);
    }

    /**
     * Obtener listado de flujos de procesos.
     */
    public function indexFlows(): JsonResponse
    {
        $flows = $this->processAuditService->listFlows();

        return response()->json([
            'success' => true,
            'data' => $flows,
        ]);
    }

    /**
     * Crear o actualizar un flujo de proceso.
     */
    public function storeFlow(StoreProcessFlowRequest $request): JsonResponse
    {
        $flow = $this->processAuditService->storeFlow($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Flujo de proceso guardado con éxito.',
            'data' => $flow,
        ]);
    }

    /**
     * Eliminar un flujo de proceso.
     */
    public function destroyFlow(int $id): JsonResponse
    {
        $this->processAuditService->deleteFlow($id);

        return response()->json([
            'success' => true,
            'message' => 'Flujo de proceso eliminado con éxito.',
        ]);
    }
}
