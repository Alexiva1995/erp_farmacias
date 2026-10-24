<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\KitchenStationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreKitchenStationRequest;
use App\Http\Requests\Restaurant\UpdateKitchenStationRequest;
use App\Http\Resources\KitchenStationResource;
use App\Models\KitchenStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KitchenStationController extends Controller
{
    public function __construct(
        protected KitchenStationServiceInterface $stationService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $stations = $this->stationService->getAll();
        return KitchenStationResource::collection($stations);
    }

    public function store(StoreKitchenStationRequest $request): KitchenStationResource
    {
        $station = $this->stationService->create($request->validated());
        return new KitchenStationResource($station);
    }

    public function show(int $id): KitchenStationResource
    {
        $station = $this->stationService->find($id);
        if (!$station) {
            abort(404, 'Estación de cocina no encontrada.');
        }
        return new KitchenStationResource($station);
    }

    public function update(UpdateKitchenStationRequest $request, KitchenStation $kitchenStation): KitchenStationResource
    {
        $updated = $this->stationService->update($kitchenStation, $request->validated());
        return new KitchenStationResource($updated);
    }

    public function destroy(KitchenStation $kitchenStation): JsonResponse
    {
        $this->stationService->delete($kitchenStation);
        return response()->json(['message' => 'Estación de cocina eliminada exitosamente.']);
    }
}
