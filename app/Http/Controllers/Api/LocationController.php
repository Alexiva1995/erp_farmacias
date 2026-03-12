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
}
