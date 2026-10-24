<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Contracts\DiningFloorServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\OpenTableOrderRequest;
use App\Http\Requests\Restaurant\StoreDiningTableRequest;
use App\Http\Requests\Restaurant\StoreDiningZoneRequest;
use App\Http\Requests\Restaurant\TransferTableRequest;
use App\Http\Requests\Restaurant\UpdateDiningLayoutRequest;
use App\Http\Resources\Restaurant\DiningTableResource;
use App\Http\Resources\Restaurant\DiningZoneResource;
use App\Models\DiningTable;
use App\Models\DiningZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningFloorController extends Controller
{
    public function __construct(
        protected DiningFloorServiceInterface $diningFloorService
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $zoneId = $request->query('zone_id');
        $overview = $this->diningFloorService->getFloorOverview($zoneId ? (int) $zoneId : null);

        return response()->json([
            'zones' => DiningZoneResource::collection($overview['zones']),
            'tables' => DiningTableResource::collection($overview['tables']),
        ]);
    }

    public function storeZone(StoreDiningZoneRequest $request): JsonResponse
    {
        $zone = $this->diningFloorService->createZone($request->validated());
        return response()->json([
            'message' => 'Zona creada correctamente.',
            'zone' => new DiningZoneResource($zone),
        ], 201);
    }

    public function updateZone(StoreDiningZoneRequest $request, DiningZone $zone): JsonResponse
    {
        $updated = $this->diningFloorService->updateZone($zone, $request->validated());
        return response()->json([
            'message' => 'Zona actualizada correctamente.',
            'zone' => new DiningZoneResource($updated),
        ]);
    }

    public function destroyZone(DiningZone $zone): JsonResponse
    {
        $this->diningFloorService->deleteZone($zone);
        return response()->json(['message' => 'Zona eliminada correctamente.']);
    }

    public function storeTable(StoreDiningTableRequest $request): JsonResponse
    {
        $table = $this->diningFloorService->createTable($request->validated());
        return response()->json([
            'message' => 'Mesa creada correctamente.',
            'table' => new DiningTableResource($table),
        ], 201);
    }

    public function updateTable(StoreDiningTableRequest $request, DiningTable $table): JsonResponse
    {
        $updated = $this->diningFloorService->updateTable($table, $request->validated());
        return response()->json([
            'message' => 'Mesa actualizada correctamente.',
            'table' => new DiningTableResource($updated),
        ]);
    }

    public function destroyTable(DiningTable $table): JsonResponse
    {
        $this->diningFloorService->deleteTable($table);
        return response()->json(['message' => 'Mesa eliminada correctamente.']);
    }

    public function updateLayout(UpdateDiningLayoutRequest $request): JsonResponse
    {
        $this->diningFloorService->updateLayout($request->validated()['tables']);
        return response()->json(['message' => 'Diseño de plano guardado correctamente.']);
    }

    public function openOrder(OpenTableOrderRequest $request, DiningTable $table): JsonResponse
    {
        $validated = $request->validated();
        $sellerId = $request->user()?->id ?? 1;
        $guestsCount = $validated['guests_count'] ?? 1;
        $clientId = $validated['client_id'] ?? null;

        $order = $this->diningFloorService->openTableOrder($table, $sellerId, $guestsCount, $clientId);

        return response()->json([
            'message' => 'Mesa abierta exitosamente.',
            'order_id' => $order->id,
            'table' => new DiningTableResource($table->fresh(['currentOrder.details.dish'])),
        ]);
    }

    public function transfer(TransferTableRequest $request, DiningTable $table): JsonResponse
    {
        $targetTable = DiningTable::findOrFail($request->validated()['target_table_id']);
        $result = $this->diningFloorService->transferOrMergeTable($table, $targetTable);

        return response()->json([
            'message' => 'Mesa transferida / fusionada exitosamente.',
            'table' => new DiningTableResource($result),
        ]);
    }

    public function release(DiningTable $table): JsonResponse
    {
        $result = $this->diningFloorService->releaseTable($table);
        return response()->json([
            'message' => 'Mesa liberada correctamente.',
            'table' => new DiningTableResource($result),
        ]);
    }
}
