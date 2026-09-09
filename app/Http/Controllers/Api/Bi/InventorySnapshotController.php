<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bi;

use App\Exports\InventorySnapshotExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\StoreInventorySnapshotRequest;
use App\Http\Resources\Bi\InventorySnapshotItemResource;
use App\Http\Resources\Bi\InventorySnapshotResource;
use App\Services\Bi\InventorySnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventorySnapshotController extends Controller
{
    public function __construct(
        protected InventorySnapshotService $service
    ) {
    }

    /**
     * Listar snapshots históricos paginados.
     */
    public function index(Request $request): JsonResponse
    {
        $snapshots = $this->service->listSnapshots($request->all());

        return response()->json([
            'data' => InventorySnapshotResource::collection($snapshots->items()),
            'meta' => [
                'total' => $snapshots->total(),
                'current_page' => $snapshots->currentPage(),
                'per_page' => $snapshots->perPage(),
                'last_page' => $snapshots->lastPage(),
            ],
        ]);
    }

    /**
     * Crear una nueva Foto Finish manual con fecha de corte.
     */
    public function store(StoreInventorySnapshotRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $snapshot = $this->service->generateSnapshot(
            cutoffDateStr: $validated['cutoff_date'],
            periodDays: (int) ($validated['period_days'] ?? 30),
            name: $validated['name'] ?? null,
            userId: auth()->id() ? (int) auth()->id() : null,
            isAutomatic: false
        );

        return response()->json([
            'message' => 'Foto Finish generada exitosamente.',
            'data' => new InventorySnapshotResource($snapshot),
        ], 201);
    }

    /**
     * Obtener el detalle de un snapshot y sus ítems filtrados/paginados.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $result = $this->service->getSnapshotDetails($id, $request->all());
        $itemsPaginator = $result['items'];

        return response()->json([
            'snapshot' => new InventorySnapshotResource($result['snapshot']),
            'items' => InventorySnapshotItemResource::collection($itemsPaginator->items()),
            'meta' => [
                'total' => $itemsPaginator->total(),
                'current_page' => $itemsPaginator->currentPage(),
                'per_page' => $itemsPaginator->perPage(),
                'last_page' => $itemsPaginator->lastPage(),
            ],
        ]);
    }

    /**
     * Obtener la auditoría integral de los 4 módulos de control estratégico.
     */
    public function auditModules(int $id): JsonResponse
    {
        $auditData = $this->service->getSnapshotAuditModules($id);

        return response()->json([
            'snapshot' => new InventorySnapshotResource($auditData['snapshot']),
            'module_1_summary' => $auditData['module_1_summary'],
            'module_2_cz_recovery' => $auditData['module_2_cz_recovery'],
            'module_3_ab_restock' => $auditData['module_3_ab_restock'],
            'module_4_margin_alerts' => $auditData['module_4_margin_alerts'],
        ]);
    }

    /**
     * Exportar los ítems de una Foto Finish a Excel (.xlsx).
     */
    public function export(int $id): BinaryFileResponse
    {
        $items = $this->service->getItemsForExport($id);
        $fileName = 'foto_finish_' . $id . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new InventorySnapshotExport($items, "Foto Finish #{$id}"),
            $fileName
        );
    }

    /**
     * Eliminar un snapshot histórico.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->deleteSnapshot($id);

        return response()->json([
            'message' => 'Foto Finish eliminada correctamente.',
        ]);
    }
}
