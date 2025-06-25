<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Resources\ResourceService;


class ResourceController extends Controller
{
    public function __construct(private ResourceService $resourceService)
    {
    }

    public function getLaboratories()
    {
        $laboratories = $this->resourceService->getLaboratories();
        return response()->json($laboratories);
    }

    public function getOrigins()
    {
        $origins = $this->resourceService->getOrigins();
        return response()->json($origins);
    }



    public function getSuppliers()
    {
        $suppliers = $this->resourceService->getSuppliers();
        return response()->json($suppliers);
    }

    public function getCategories()
    {
        $categories = $this->resourceService->getCategories();
        return response()->json($categories);
    }
}
