<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Services\LocationServices;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    /**
     * Location service.
     * 
     * @var LocationServices
     */
    protected $locationServices;

    /**
     * Constructor.
     * 
     * @param LocationServices $locationServices
     */
    public function __construct(LocationServices $locationServices)
    {
        $this->locationServices = $locationServices;
    }

    /**
     * Display a listing of the resource.
     * 
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return LocationResource::collection($this->locationServices->getAllLocations());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\StoreLocationRequest $request
     * @return \App\Http\Resources\LocationResource
     */
    public function store(\App\Http\Requests\StoreLocationRequest $request): LocationResource
    {
        $location = $this->locationServices->createLocation($request->validated());

        return new LocationResource($location);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\UpdateLocationRequest $request
     * @param int $id
     * @return \App\Http\Resources\LocationResource
     */
    public function update(\App\Http\Requests\UpdateLocationRequest $request, int $id): LocationResource
    {
        $location = $this->locationServices->updateLocation($id, $request->validated());

        return new LocationResource($location);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \App\Http\Resources\LocationResource
     */
    public function show(int $id): LocationResource
    {
        $location = $this->locationServices->getLocationById($id);

        return new LocationResource($location);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $this->locationServices->deleteLocation($id);

        return response()->json([
            'message' => 'Ubicación eliminada con éxito.'
        ]);
    }
}
